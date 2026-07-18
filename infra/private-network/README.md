# Private Database Network

These artifacts prepare a private path from the Serverless Container VPC through a dedicated YC WireGuard gateway to MariaDB bound to the VPS WireGuard address. They are not applied by CI and contain no production addresses or keys.

## Safety Invariants

- `network.env.example` contains mandatory placeholders, not production values.
- WireGuard private keys are generated on their respective hosts and are never copied into this repository.
- `render-config.php` rejects symlinks and broad key/config permissions, creates a nonpredictable `0700` directory under an existing output parent, and atomically writes every generated file as `0600` without printing key material.
- MariaDB Compose refuses to start without `MARIADB_BIND_ADDRESS`; validation requires it to equal the VPS WireGuard address.
- The VPS firewall protects both INPUT and Docker's DNAT/FORWARD path and drops every TCP connection to port `3306` except traffic arriving over `wg0` from the YC private subnet.
- The public API Gateway continues to block `/internal/*`; recurring work uses the direct IAM-authenticated Serverless Container path only.

## Local Validation

Validate the source-controlled templates without filling production data:

```sh
php infra/private-network/validate-config.php --check-template infra/private-network/network.env.example infra/mariadb/.env.example
php tests/private-network-config.php
```

For rollout, create ignored working files and replace every `__REQUIRED...__` value. Do not put private keys into either env file:

```sh
cp infra/private-network/network.env.example infra/private-network/network.env
cp infra/mariadb/.env.example infra/mariadb/.env
chmod 600 infra/private-network/network.env infra/mariadb/.env
```

Before YC allocates the gateway public IP and before WireGuard keys exist, use the bootstrap stage. It validates every operator-chosen value while allowing placeholders only for those three generated values:

```sh
php infra/private-network/validate-config.php --stage=bootstrap infra/private-network/network.env
```

After writing the allocated IP and both public keys, the final validator is mandatory:

```sh
php infra/private-network/validate-config.php --stage=final infra/private-network/network.env infra/mariadb/.env
docker compose --env-file infra/mariadb/.env -f infra/mariadb/docker-compose.yml config --quiet
```

Never execute `source`, `. network.env` or `eval` on these files. `validate-config.php` rejects shell metacharacters, and rollout commands obtain individual non-secret values through `config-value.php`.

## YC Provisioning Plan

The following is a production rollout plan, not an instruction to run it before backup, review and approval. All variables come from the validated ignored `network.env` except the two IDs read after resource creation.

```sh
config_get() {
  php infra/private-network/config-value.php --stage=bootstrap infra/private-network/network.env "$1"
}
YC_FOLDER_ID="$(config_get YC_FOLDER_ID)"
YC_ZONE="$(config_get YC_ZONE)"
YC_NETWORK_NAME="$(config_get YC_NETWORK_NAME)"
YC_PRIVATE_SUBNET_NAME="$(config_get YC_PRIVATE_SUBNET_NAME)"
YC_PRIVATE_SUBNET_CIDR="$(config_get YC_PRIVATE_SUBNET_CIDR)"
YC_ROUTE_TABLE_NAME="$(config_get YC_ROUTE_TABLE_NAME)"
YC_GATEWAY_VM_NAME="$(config_get YC_GATEWAY_VM_NAME)"
YC_GATEWAY_ADDRESS_NAME="$(config_get YC_GATEWAY_ADDRESS_NAME)"
YC_GATEWAY_SECURITY_GROUP_NAME="$(config_get YC_GATEWAY_SECURITY_GROUP_NAME)"
YC_GATEWAY_PRIVATE_IP="$(config_get YC_GATEWAY_PRIVATE_IP)"
YC_GATEWAY_SSH_PUBLIC_KEY_FILE="$(config_get YC_GATEWAY_SSH_PUBLIC_KEY_FILE)"
OPERATOR_SSH_CIDR="$(config_get OPERATOR_SSH_CIDR)"
VPS_PUBLIC_IP="$(config_get VPS_PUBLIC_IP)"
VPS_WG_ADDRESS_CIDR="$(config_get VPS_WG_ADDRESS_CIDR)"
WIREGUARD_PORT="$(config_get WIREGUARD_PORT)"
VPS_WG_ADDRESS="${VPS_WG_ADDRESS_CIDR%/*}"

yc vpc network create --folder-id "$YC_FOLDER_ID" --name "$YC_NETWORK_NAME"
yc vpc subnet create --folder-id "$YC_FOLDER_ID" --name "$YC_PRIVATE_SUBNET_NAME" --zone "$YC_ZONE" --network-name "$YC_NETWORK_NAME" --range "$YC_PRIVATE_SUBNET_CIDR"
yc vpc address create --folder-id "$YC_FOLDER_ID" --name "$YC_GATEWAY_ADDRESS_NAME" --external-ipv4 "zone=$YC_ZONE" --deletion-protection

YC_GATEWAY_PUBLIC_IP="$(yc vpc address get --folder-id "$YC_FOLDER_ID" --name "$YC_GATEWAY_ADDRESS_NAME" --format json | jq -er '.external_ipv4_address.address')"
yc vpc security-group create --folder-id "$YC_FOLDER_ID" --name "$YC_GATEWAY_SECURITY_GROUP_NAME" --network-name "$YC_NETWORK_NAME" \
  --rule "direction=ingress,port=22,protocol=tcp,v4-cidrs=$OPERATOR_SSH_CIDR" \
  --rule "direction=ingress,port=$WIREGUARD_PORT,protocol=udp,v4-cidrs=$VPS_PUBLIC_IP/32" \
  --rule "direction=ingress,port=3306,protocol=tcp,v4-cidrs=$YC_PRIVATE_SUBNET_CIDR" \
  --rule "direction=egress,protocol=any,v4-cidrs=0.0.0.0/0"
YC_GATEWAY_SECURITY_GROUP_ID="$(yc vpc security-group get --folder-id "$YC_FOLDER_ID" --name "$YC_GATEWAY_SECURITY_GROUP_NAME" --format json | jq -er '.id')"

yc compute instance create --folder-id "$YC_FOLDER_ID" --name "$YC_GATEWAY_VM_NAME" --zone "$YC_ZONE" \
  --cores 2 --memory 2GB --core-fraction 20 \
  --create-boot-disk "image-folder-id=standard-images,image-family=ubuntu-2404-lts,size=15GB,auto-delete=true" \
  --network-interface "subnet-name=$YC_PRIVATE_SUBNET_NAME,ipv4-address=$YC_GATEWAY_PRIVATE_IP,nat-address=$YC_GATEWAY_PUBLIC_IP,security-group-ids=$YC_GATEWAY_SECURITY_GROUP_ID" \
  --ssh-key "$YC_GATEWAY_SSH_PUBLIC_KEY_FILE" \
  --metadata-from-file "user-data=infra/private-network/gateway/cloud-init.yaml"

yc vpc route-table create --folder-id "$YC_FOLDER_ID" --name "$YC_ROUTE_TABLE_NAME" --network-name "$YC_NETWORK_NAME" \
  --route "destination=$VPS_WG_ADDRESS/32,next-hop=$YC_GATEWAY_PRIVATE_IP"
yc vpc subnet update --folder-id "$YC_FOLDER_ID" --name "$YC_PRIVATE_SUBNET_NAME" --route-table-name "$YC_ROUTE_TABLE_NAME"
```

Write the allocated `YC_GATEWAY_PUBLIC_IP` and both generated WireGuard public keys into the ignored `network.env`, then run strict validation again. The example placeholders must never be passed to `yc`, `wg`, `nft` or Docker Compose.

## Host Configuration Plan

Install WireGuard and generate each private key only on its destination host:

```sh
# On the YC gateway
sudo install -d -m 700 /etc/wireguard /etc/linka-private-network
sudo sh -c 'umask 077; test -f /etc/wireguard/linka-gateway.key || wg genkey > /etc/wireguard/linka-gateway.key'
sudo sh -c 'wg pubkey < /etc/wireguard/linka-gateway.key'

# On the VPS
sudo install -d -m 700 /etc/wireguard /etc/linka-private-network
sudo sh -c 'umask 077; test -f /etc/wireguard/linka-vps.key || wg genkey > /etc/wireguard/linka-vps.key'
sudo sh -c 'wg pubkey < /etc/wireguard/linka-vps.key'
```

After exchanging only the public keys, copy this directory and the ignored, non-secret `network.env` to each host. Render and validate on each host:

```sh
# Gateway
gateway_rendered="$(php render-config.php gateway network.env /tmp)"
sudo nft --check --file "$gateway_rendered/firewall.nft"
sudo install -m 600 "$gateway_rendered/wg0.conf" /etc/wireguard/wg0.conf
sudo install -m 600 "$gateway_rendered/firewall.nft" /etc/linka-private-network/firewall.nft
sudo install -m 600 "$gateway_rendered/99-linka-forwarding.conf" /etc/sysctl.d/99-linka-forwarding.conf
sudo install -m 644 gateway/linka-private-firewall.service /etc/systemd/system/linka-private-firewall.service
sudo sysctl --system
sudo systemctl daemon-reload
sudo systemctl enable --now linka-private-firewall.service wg-quick@wg0.service

# VPS: start the private tunnel and temporary dual-path proxy without touching the running MariaDB container or its legacy path.
vps_rendered="$(php render-config.php vps network.env /tmp)"
sudo nft --check --file "$vps_rendered/firewall.nft"
sudo nft --check --file "$vps_rendered/cutover-firewall.nft"
test -x /usr/lib/systemd/systemd-socket-proxyd
sudo install -m 600 "$vps_rendered/wg0.conf" /etc/wireguard/wg0.conf
sudo install -m 600 "$vps_rendered/firewall.nft" /etc/linka-private-network/firewall.nft
sudo install -m 600 "$vps_rendered/cutover-firewall.nft" /etc/linka-private-network/cutover-firewall.nft
sudo install -m 600 "$vps_rendered/linka-db-private-proxy.socket" /etc/systemd/system/linka-db-private-proxy.socket
sudo install -m 600 "$vps_rendered/linka-db-private-proxy.service" /etc/systemd/system/linka-db-private-proxy.service
sudo install -m 644 vps/linka-private-firewall.service /etc/systemd/system/linka-private-firewall.service
sudo install -m 644 vps/linka-private-cutover-firewall.service /etc/systemd/system/linka-private-cutover-firewall.service
sudo systemctl daemon-reload
sudo systemctl enable --now wg-quick@wg0.service
sudo systemctl enable --now linka-private-cutover-firewall.service linka-db-private-proxy.socket
```

The temporary socket proxy listens on a separate private port and forwards to the unchanged current MariaDB publication. An early nftables redirect exposes it as the final WireGuard `3306` endpoint while the legacy path remains available. This creates dual paths without recreating or stopping MariaDB. Review the VPS's existing nftables/iptables policy before loading either table; do not weaken unrelated rules.

## MariaDB Cutover Plan

Do not run `docker compose up` during the network cutover. First prove the private proxy, switch the Serverless revision, and require `/readyz.php` to remain `200`. Only then close the legacy public path by enabling the final Docker-aware firewall:

```sh
sudo systemctl enable --now linka-private-firewall.service
```

The source Compose file already defines the converged private bind. Validate it now, but apply it only at the next separately reviewed MariaDB restart or maintenance event. The temporary proxy continues to serve the private endpoint in the meantime, so the connectivity cutover itself has no database outage:

```sh
./backup.sh
php /etc/linka-private-network/validate-config.php /etc/linka-private-network/network.env .env
docker compose config --quiet
```

At the later approved convergence event:

```sh
# The current legacy Docker publication already includes the WireGuard host address.
# Remove only the temporary redirect first, then verify direct WireGuard connectivity.
sudo systemctl disable --now linka-private-cutover-firewall.service
# Verify direct WireGuard:3306 and /readyz.php before changing Compose.
docker compose up -d mariadb
docker compose ps
sudo systemctl disable --now linka-db-private-proxy.socket
```

Before disabling the temporary proxy, verify that the new Compose bind accepts `3306` directly on the WireGuard address. The final firewall must already block public and Docker-forwarded `3306` traffic.

## External Firewalls And Logs

Host nftables is not the outer security boundary. After private readiness succeeds, update the VPS provider firewall/security group so public TCP `3306` is denied from every source. Keep only WireGuard UDP from `YC_GATEWAY_PUBLIC_IP/32` and the separately approved SSH source. Do not add a public TCP `3306` exception for YC; database traffic must arrive inside WireGuard.

Verify from a host outside both networks that TCP `3306` is closed, then verify from the Serverless VPC that the WireGuard endpoint remains reachable. Preserve the provider firewall rule identifiers in the rollout record so rollback can restore only the prior reviewed rules.

Set the Yandex Cloud log group that receives container/security logs to the policy's 30-day period:

```sh
: "${YC_LOG_GROUP_ID:?set the reviewed log group id}"
yc logging group update --id "$YC_LOG_GROUP_ID" --retention-period 720h
yc logging group get --id "$YC_LOG_GROUP_ID"
```

The first command is a production mutation and remains plan-only until approval.

## Serverless Cutover

Create a new Lockbox version that changes only `WORDPRESS_DB_HOST` to `<VPS_WIREGUARD_ADDRESS>:3306`, then deploy a Serverless Container revision with `--network-name "$YC_NETWORK_NAME"` while preserving every current image, resource, environment, secret and mount setting. Do not issue an incomplete `revision deploy`: omitted settings are not inherited safely.

Verify in this order:

```sh
curl --fail --silent --show-error https://nkolinka.ru/healthz.php
curl --silent --show-error --write-out '\n%{http_code}\n' https://nkolinka.ru/readyz.php
curl --silent --show-error --output /dev/null --write-out '%{http_code}\n' https://nkolinka.ru/internal/run-recurring-donations
```

Expected results are `ok` for liveness, `{"status":"ready"}` with HTTP `200` for readiness, and public `404` for `/internal/*`. Invoke recurring work only through the direct container URL with the timer service account's IAM authentication.
