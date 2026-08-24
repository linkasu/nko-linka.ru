<?php
/**
 * One-time notice about the technical cookies used by the site.
 */

if (!defined('ABSPATH')) {
    exit;
}

const LINKA_NKO_COOKIE_NOTICE_NAME = 'linka_technical_cookies_notice';
const LINKA_NKO_COOKIE_NOTICE_MAX_AGE = 15552000;

add_action('wp_footer', 'linka_nko_render_cookie_notice', 20);

function linka_nko_render_cookie_notice(): void
{
    if (isset($_COOKIE[LINKA_NKO_COOKIE_NOTICE_NAME]) && (string) $_COOKIE[LINKA_NKO_COOKIE_NOTICE_NAME] === '1') {
        return;
    }
    ?>
    <aside class="linka-cookie-notice" role="region" aria-label="Уведомление о cookies">
      <p>Сайт использует только технически необходимые cookies для корректной и безопасной работы. Подробнее в <a href="<?php echo esc_url(home_url('/privacy-policy/')); ?>">политике обработки персональных данных</a>.</p>
      <button type="button" class="linka-cookie-notice__button">Понятно</button>
    </aside>
    <style>
      .linka-cookie-notice{position:fixed;z-index:1000;right:20px;bottom:20px;display:flex;align-items:center;gap:18px;width:min(720px,calc(100% - 40px));padding:18px 20px;color:#17313a;background:#fff;border:1px solid #d9e8e5;border-radius:18px;box-shadow:0 12px 36px rgba(23,49,58,.18)}
      .linka-cookie-notice p{margin:0}.linka-cookie-notice__button{flex:0 0 auto;padding:10px 18px;color:#fff;background:#0e7a83;border:0;border-radius:999px;font:inherit;font-weight:700;cursor:pointer}.linka-cookie-notice__button:focus{outline:3px solid #9cc449;outline-offset:3px}
      @media(max-width:640px){.linka-cookie-notice{right:16px;bottom:16px;align-items:stretch;flex-direction:column;width:calc(100% - 32px)}.linka-cookie-notice__button{align-self:flex-start}}
    </style>
    <script>
      (() => {
        const notice = document.querySelector('.linka-cookie-notice');
        const button = notice?.querySelector('.linka-cookie-notice__button');
        button?.addEventListener('click', () => {
          const secure = location.protocol === 'https:' ? '; Secure' : '';
          document.cookie = '<?php echo esc_js(LINKA_NKO_COOKIE_NOTICE_NAME); ?>=1; Path=/; Max-Age=<?php echo esc_js((string) LINKA_NKO_COOKIE_NOTICE_MAX_AGE); ?>; SameSite=Lax' + secure;
          notice.remove();
        });
      })();
    </script>
    <?php
}
