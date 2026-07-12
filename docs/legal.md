# Legal Notes

This is not legal advice. It records implementation assumptions for the website.

## Public Entity Data

- Full name: Автономная некоммерческая организация в сфере развития доступной среды и повышения коммуникативных навыков людей с ОВЗ "Линка".
- Short name: АНО "Линка".
- OGRN: `1267800046975`.
- INN: `7840128432`.
- KPP: `784001001`.
- Registration date: `26.06.2026`.
- Location: Санкт-Петербург.
- Email: `feedback@linka.su`.
- Chairperson: Иван Александрович Бакаидов.

## Legal Address Publication

The full legal address is present in public EGRUL documents. For v1:

- Show the full address on the "Реквизиты" page.
- Show the full address in the personal data policy if the site processes personal data.
- Do not duplicate the full address in every footer block.

## Required Public Sections For Trust

- Documents.
- Reports.
- Requisites.
- Contacts.
- Personal data policy.
- Donation offer, if the site shows voluntary donations.

## Donations

- Only voluntary donations for statutory nonprofit activity are allowed on the site.
- Donation text must not look like payment for goods, services, courses, consultations, software or digital products.
- Before enabling real payments, publish a donation offer and update the personal data policy.
- Active payment form is enabled after YooKassa approval through runtime secrets; donations must remain voluntary and not be presented as payment for goods or services.
- Do not issue receipts that describe voluntary donations as goods, works, services, courses, consultations, software or digital products.
- Monthly voluntary donations require explicit donor consent for the amount, frequency, saved payment method, and cancellation process. They must be enabled only after YooKassa enables production autopayments for the shop.
- Monthly donation cancellation must be self-service through a protected management link sent to the donor after the first successful payment. Cancellation by email can remain a fallback support channel, but it must not be the only way to unlink the saved payment method.
- The site must not store bank card details. It may store YooKassa payment identifiers and saved payment method identifiers only while needed for recurring donations; the saved payment method identifier must be removed from the site database when the donor cancels the monthly donation.

## Nonprofit Transparency

Federal Law 7-FZ requires nonprofit reporting to state bodies and includes public reporting through authorized resources. The site should provide documents and reports for transparency, but final statutory report publication procedure should be checked before first annual report publication.
