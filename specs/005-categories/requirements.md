# 005 — Categories: Requirements

> Status: Approved (2026-11-22), with readings R1–R7; Appendix A is final (the Arabic still benefits from a native reader)
> Scope: `app/Modules/Ledger` (categories table, catalogue file, sync command, read endpoint). Also `CLAUDE.md`, the README, `contract.md` and the Postman collection. Nothing changes in `Identity` or `Household`.

## Context

Every money entry of spec 006 points to a category. Spec 003 (D13) decided the shape: one global list, seeded in the database and shared by every book, from which the user only picks. This spec builds that list, the command that keeps the database in step with it, and the endpoint the app reads it from.

On 2026-11-22 the owner changed D13 from two levels to **three**: top category → group → leaf. The list itself is in **Appendix A**.

Spec 003's decisions D1–D14 stand, except D13, which this spec replaces with C1–C11.

### Owner decisions

Given on 2026-11-22. Not to be reopened:

| # | Topic | Decision |
|---|---|---|
| C1 | Three levels everywhere | Every category sits at exactly one of three levels: top category, group, leaf. Every top has at least one group, and every group at least one leaf. Only a leaf can be picked for an entry (spec 006). |
| C2 | "Other" | Every group ends with an "Other" leaf (code `<top>.<group>.other`). Picking it (spec 006) requires a name typed by the user, saved with the entry. D13's "Other" parent becomes the **Miscellaneous** top category with an **Other** group (`misc.other.other`). |
| C3 | Type | Each top category has a type: `expense`, `income` or `saving`. Its groups and leaves share it. |
| C4 | International | No country- or supplier-specific names ("Electricity", never a utility company's name). Holidays of several faiths (Muslim, Christian, Jewish) each have their own group under **Celebrations & giving**. |
| C5 | Translation keys, translated by the app | The database stores no category names. Each category row stores a **translation key** (`categories.<code>.label`, e.g. `categories.food.groceries.supermarket.label`; the `.label` suffix lets `categories.food.label` and `categories.food.groceries.label` live side by side in nested translation files), and the API sends that key. The frontend translates it at runtime from its own translation files (`fr.json`, `en.json`, `ar.json`), like every other key. This keeps the "API sends keys, no user-facing text" rule with no exception. Consequence: a new category needs its translations in the app before it shows a name. |
| C6 | Emoji | Every top category and group has an emoji. Leaves have one too, except holiday leaves, whose group's emoji carries the meaning, and "Other" leaves use 📝. Savings uses 🪙. The API serves no colours; the app chooses them. |
| C7 | Stable codes | A category's public identifier is its dotted code (`food.groceries.supermarket`). A code is never renamed and never reused. |
| C8 | Changing the list | The list lives in one file in the Ledger module. A command brings the database in line with it. A category removed from the file is **retired**, never deleted, because entries may point to it. |
| C9 | No user categories | Users cannot create, rename, reorder or hide categories. |
| C10 | Comment on entries | Every entry of spec 006 has an optional free-text comment, separate from the "Other" name. Recorded here so spec 006 includes it; not built in this spec. |
| C11 | Later specs | Suggesting "Other" names already typed in the same book belongs to spec 006 or later. Grouping "Other" entries in summaries belongs to spec 007. |

### Readings of the decisions (confirm at approval)

| # | Point | Reading used |
|---|---|---|
| R1 | Who can read the list | Any signed-in account, in cookie or token mode. The list is global, so the route is not nested under a book. |
| R2 | Shape of the answer | The whole tree in one call, tops → groups → leaves in file order, each with its `translation_key`. No names. |
| R7 | Missing translation | If the app has no translation for a key, it shows the parent's translated name (and "Other" for an unknown leaf) rather than the raw key. This is a frontend rule, recorded here for the frontend spec. |
| R3 | Retired categories | Included in the answer with `retired: true`, so old entries can still be displayed. The app does not offer them for new entries (spec 006 refuses them). |
| R4 | Caching | The answer carries an `ETag`. A request with a matching `If-None-Match` gets `304` with no body. The list changes only when the sync command runs. |
| R5 | Changing a type | Moving an existing code to another type (for example `expense` to `income`) is refused, because it would silently change past summaries. A new code is needed instead. |
| R6 | Tests | Tests build categories with factories. One test checks that the shipped file is valid, matches Appendix A and syncs. |

## User stories

- **US-1** — As someone recording an entry, I want a clear list of categories with icons, so that I find the right one in a few taps.
- **US-2** — As the app, I want the whole tree in one call that I can cache, so that the category picker opens instantly.
- **US-3** — As the app's owner, I want to change the list without a migration or an app release, and without breaking old entries.

## Acceptance criteria (EARS)

### US-1, US-2 — Reading the list

- **REQ-1.1** WHEN a signed-in user calls `GET /api/v1/categories` THE SYSTEM SHALL answer `200` with every top category in file order, each with its groups in file order, each with its leaves in file order.
- **REQ-1.2** Every category in the answer SHALL carry `code`, `translation_key`, `emoji` (a string, or `null` for holiday leaves) and `retired` (boolean). Top categories SHALL also carry `type`. Leaves SHALL also carry `is_other` (true only for "Other" leaves).
- **REQ-1.3** IF the caller is not signed in THEN THE SYSTEM SHALL answer `401 auth.unauthenticated`.
- **REQ-1.4** THE SYSTEM SHALL send an `ETag` that changes whenever the list changes. WHEN a request carries a matching `If-None-Match` THE SYSTEM SHALL answer `304` with no body.
- **REQ-1.5** The answer SHALL be the same in cookie mode and token mode, and for every account.
- **REQ-1.6** WHILE a category is retired THE SYSTEM SHALL still return it, with `retired: true`. A retired group or top category SHALL make all its descendants appear retired.

### US-3 — The catalogue file and the sync command

- **REQ-2.1** The list SHALL live in one file inside `app/Modules/Ledger` (format chosen in the design), which is the only source of truth for categories.
- **REQ-2.2** WHEN the sync command runs on a valid file THE SYSTEM SHALL, in one database transaction:
  - create categories that are new in the file;
  - update the emoji and order of existing ones, matched by code (the translation key follows the code and never changes);
  - retire categories that are no longer in the file;
  - un-retire categories that come back.
  It SHALL then print how many were created, updated, retired, restored and left unchanged.
- **REQ-2.3** WHEN the sync command runs twice on the same file THE SYSTEM SHALL change nothing the second time.
- **REQ-2.4** IF the file is invalid THEN THE SYSTEM SHALL change nothing, list every problem, and exit with a non-zero code. The file is invalid when:
  - a category is not at exactly one of the three levels, or a top or group has no children;
  - a code appears twice, a code segment does not match `^[a-z][a-z0-9_]*$` or is `label`, or a child's code does not start with its parent's code;
  - a top category has no type, or a type other than `expense`, `income` or `saving`;
  - a top category or a group has no emoji;
  - a group has no `other` leaf, or its `other` leaf is not its last leaf.
- **REQ-2.5** IF the file gives an existing code another type THEN THE SYSTEM SHALL refuse the whole sync, as in REQ-2.4 (R5).
- **REQ-2.6** THE SYSTEM SHALL never delete a category row.
- **REQ-2.7** The README's setup steps and the deploy steps SHALL run the sync command right after the migrations, so a fresh environment has the list.
- **REQ-2.8** The shipped file SHALL contain exactly the categories of Appendix A, with the same codes, emoji, types and order. It SHALL contain no names.
- **REQ-2.9** Every category row SHALL store its translation key, `categories.<code>.label`, set by the sync command.
- **REQ-2.10** The English, French and Arabic names of Appendix A SHALL be handed to the frontend as translation entries (`categories.<code>.label` in `en.json`, `fr.json` and `ar.json`). The backend ships a generated `specs/005-categories/translations/{en,fr,ar}.json` that the frontend copies; the backend itself serves no names.

### Documentation and tests

- **REQ-3.1** `CLAUDE.md` SHALL be updated in this spec:
  - the "Seeding" rule describes three levels, stable codes, retiring instead of deleting, and the sync command;
  - categories store translation keys, never names (C5);
  - the "Current state" section says categories exist and transactions come with spec 006.
- **REQ-3.2** `specs/005-categories/contract.md` and the Postman collection SHALL describe `GET /api/v1/categories`, including `304`.
- **REQ-3.3** The README SHALL explain how to change the category list: edit the file, run the command, never rename or reuse a code.
- **REQ-3.4** The endpoint SHALL have feature tests for success (full tree, order, fields), `401`, `ETag` and `304`, in both modes. The command SHALL have tests for create, update, retire, restore, idempotence, every invalid case of REQ-2.4, and the type change of REQ-2.5.
- **REQ-3.5** The architecture tests SHALL still pass: `Ledger` may use `Household`, `Identity` and `Shared`; nothing uses `Ledger`.

Checklist — have these been covered?
- [x] Validation errors (per field, with stable codes) — the endpoint takes no input; the command reports file problems (REQ-2.4)
- [x] Unauthenticated access (401), cookie and token modes (REQ-1.3, REQ-1.5)
- [x] Access to another household's data — not applicable: the list is global and the same for everyone (REQ-1.5)
- [x] Rate limits / repeated requests / duplicates — default API limit; `ETag`/`304` (REQ-1.4); idempotent sync (REQ-2.3)
- [x] Money edge cases — not applicable
- [x] Date & timezone edge cases — not applicable
- [x] Deleting / changing referenced data — retire, never delete (REQ-2.2, REQ-2.6); no type change (REQ-2.5)

## Non-goals

- Entries, transactions and the comment field (spec 006).
- Saving or suggesting names typed for "Other" (spec 006 or later).
- Summaries and how they group categories (spec 007).
- Categories created, renamed, reordered or hidden by users, globally or per book.
- Colours, and names in languages other than English, French and Arabic.
- Right-to-left layout for Arabic in the app (a frontend spec).
- The frontend.

## Open questions

1. Is Appendix A final, including the French and Arabic names? (The Arabic is Modern Standard Arabic; a native reader should check it.)

## Appendix A — The category list

126 leaves in 33 groups under 11 top categories. Rows in **bold** are top categories, rows in *italics* are groups, other rows are leaves. Groups and leaves share their top category's type. The English, French and Arabic columns are the **frontend translations** for `categories.<code>.label` (REQ-2.10); they are not stored in the database.

| Code | Emoji | English | Français | العربية | Type |
|---|---|---|---|---|---|
| `food` | 🛒 | **Food** | **Alimentation** | **الطعام** | expense |
| `food.groceries` | 🏪 | *Groceries* | *Courses* | *البقالة* | |
| `food.groceries.supermarket` | 🥫 | Supermarket | Supermarché | السوبرماركت | |
| `food.groceries.meat_fish` | 🥩 | Meat & fish | Viande & poisson | اللحوم والأسماك | |
| `food.groceries.fruit_vegetables` | 🥕 | Fruit & vegetables | Fruits & légumes | الفواكه والخضروات | |
| `food.groceries.bakery` | 🥖 | Bakery | Boulangerie | المخبز | |
| `food.groceries.dairy` | 🧀 | Dairy | Produits laitiers | منتجات الألبان | |
| `food.groceries.other` | 📝 | Other | Autre | أخرى | |
| `food.eating_out` | 🍽️ | *Eating out* | *Repas à l'extérieur* | *الأكل خارج المنزل* | |
| `food.eating_out.cafes` | ☕ | Cafés | Cafés | المقاهي | |
| `food.eating_out.restaurants` | 🍝 | Restaurants | Restaurants | المطاعم | |
| `food.eating_out.takeaway` | 🥡 | Takeaway & delivery | À emporter & livraison | الطلبات الخارجية والتوصيل | |
| `food.eating_out.other` | 📝 | Other | Autre | أخرى | |
| `home` | 🏠 | **Home & bills** | **Logement & factures** | **السكن والفواتير** | expense |
| `home.housing` | 🔑 | *Housing* | *Logement* | *السكن* | |
| `home.housing.rent` | 🏠 | Rent | Loyer | الإيجار | |
| `home.housing.mortgage` | 🏦 | Mortgage | Crédit immobilier | القرض العقاري | |
| `home.housing.building_charges` | 🏢 | Building charges | Charges de copropriété | رسوم الملكية المشتركة | |
| `home.housing.other` | 📝 | Other | Autre | أخرى | |
| `home.utilities` | 💡 | *Utilities* | *Énergie & eau* | *الطاقة والمياه* | |
| `home.utilities.electricity` | ⚡ | Electricity | Électricité | الكهرباء | |
| `home.utilities.water` | 💧 | Water | Eau | الماء | |
| `home.utilities.gas_heating` | 🔥 | Gas & heating | Gaz & chauffage | الغاز والتدفئة | |
| `home.utilities.other` | 📝 | Other | Autre | أخرى | |
| `home.telecom` | 📡 | *Telecom* | *Télécom* | *الاتصالات* | |
| `home.telecom.internet` | 🌐 | Internet | Internet | الإنترنت | |
| `home.telecom.mobile_phone` | 📱 | Mobile phone | Téléphone mobile | الهاتف الجوال | |
| `home.telecom.other` | 📝 | Other | Autre | أخرى | |
| `family` | 👨‍👩‍👧 | **Family, pets & household** | **Famille, animaux & maison** | **العائلة والحيوانات والمنزل** | expense |
| `family.children` | 👶 | *Children* | *Enfants* | *الأطفال* | |
| `family.children.baby_supplies` | 🍼 | Baby supplies | Articles pour bébé | مستلزمات الرضّع | |
| `family.children.toys` | 🧸 | Toys | Jouets | الألعاب | |
| `family.children.childcare` | 🧑‍🍼 | Childcare | Garde d'enfants | رعاية الأطفال | |
| `family.children.pocket_money` | 💵 | Pocket money | Argent de poche | مصروف الجيب | |
| `family.children.other` | 📝 | Other | Autre | أخرى | |
| `family.education` | 🎓 | *Education* | *Éducation* | *التعليم* | |
| `family.education.school_fees` | 🏫 | School fees | Frais de scolarité | الرسوم المدرسية | |
| `family.education.supplies_books` | ✏️ | Supplies & books | Fournitures & livres | الأدوات المدرسية والكتب | |
| `family.education.tutoring` | 📚 | Tutoring | Cours particuliers | الدروس الخصوصية | |
| `family.education.university` | 🎓 | University | Université | الجامعة | |
| `family.education.activities` | 🎨 | Activities | Activités | الأنشطة | |
| `family.education.other` | 📝 | Other | Autre | أخرى | |
| `family.pets` | 🐾 | *Pets* | *Animaux* | *الحيوانات الأليفة* | |
| `family.pets.pet_food` | 🦴 | Pet food | Nourriture | طعام الحيوانات | |
| `family.pets.vet` | 🐕 | Vet | Vétérinaire | الطبيب البيطري | |
| `family.pets.accessories` | 🧶 | Accessories | Accessoires | المستلزمات | |
| `family.pets.other` | 📝 | Other | Autre | أخرى | |
| `family.household` | 🧹 | *Household* | *Maison* | *المنزل* | |
| `family.household.cleaning` | 🧼 | Cleaning products | Produits d'entretien | مواد التنظيف | |
| `family.household.repairs` | 🛠️ | Repairs & maintenance | Réparations & entretien | الإصلاحات والصيانة | |
| `family.household.furniture_appliances` | 🛋️ | Furniture & appliances | Meubles & électroménager | الأثاث والأجهزة المنزلية | |
| `family.household.other` | 📝 | Other | Autre | أخرى | |
| `transport` | 🚗 | **Transport** | **Transport** | **النقل** | expense |
| `transport.car` | 🚘 | *Car* | *Voiture* | *السيارة* | |
| `transport.car.fuel` | ⛽ | Fuel | Carburant | الوقود | |
| `transport.car.maintenance` | 🔧 | Maintenance | Entretien | الصيانة | |
| `transport.car.parking_tolls` | 🅿️ | Parking & tolls | Parking & péages | مواقف السيارات ورسوم الطرق | |
| `transport.car.other` | 📝 | Other | Autre | أخرى | |
| `transport.getting_around` | 🚌 | *Getting around* | *Déplacements* | *التنقلات* | |
| `transport.getting_around.taxi` | 🚕 | Taxi & ride-hailing | Taxi & VTC | سيارات الأجرة والنقل عبر التطبيقات | |
| `transport.getting_around.public_transport` | 🚆 | Public transport | Transports en commun | النقل العام | |
| `transport.getting_around.other` | 📝 | Other | Autre | أخرى | |
| `personal` | 🧍 | **Personal & health** | **Santé & personnel** | **الصحة والعناية الشخصية** | expense |
| `personal.health` | 🩺 | *Health* | *Santé* | *الصحة* | |
| `personal.health.pharmacy` | 💊 | Pharmacy | Pharmacie | الصيدلية | |
| `personal.health.doctor` | 👨‍⚕️ | Doctor | Médecin | الطبيب | |
| `personal.health.dentist` | 🦷 | Dentist | Dentiste | طبيب الأسنان | |
| `personal.health.lab_tests` | 🔬 | Lab tests | Analyses | التحاليل الطبية | |
| `personal.health.optician` | 👓 | Optician | Opticien | أخصائي النظارات | |
| `personal.health.other` | 📝 | Other | Autre | أخرى | |
| `personal.care` | 💇 | *Personal care* | *Soins personnels* | *العناية الشخصية* | |
| `personal.care.toiletries` | 🧴 | Toiletries | Produits d'hygiène | مستحضرات النظافة | |
| `personal.care.hairdresser_beauty` | 💅 | Hairdresser & beauty | Coiffeur & beauté | الحلاقة والتجميل | |
| `personal.care.other` | 📝 | Other | Autre | أخرى | |
| `personal.clothing` | 👕 | *Clothing* | *Vêtements* | *الملابس* | |
| `personal.clothing.clothes` | 👗 | Clothes | Vêtements | الملابس | |
| `personal.clothing.shoes` | 👟 | Shoes | Chaussures | الأحذية | |
| `personal.clothing.children_clothes` | 🧒 | Children's clothes | Vêtements enfants | ملابس الأطفال | |
| `personal.clothing.other` | 📝 | Other | Autre | أخرى | |
| `entertainment` | 🎭 | **Entertainment & hobbies** | **Loisirs** | **الترفيه والهوايات** | expense |
| `entertainment.outings` | 🎟️ | *Outings* | *Sorties* | *النزهات* | |
| `entertainment.outings.cinema_shows` | 🎬 | Cinema & shows | Cinéma & spectacles | السينما والعروض | |
| `entertainment.outings.events` | 🎡 | Events & activities | Événements & activités | الفعاليات والأنشطة | |
| `entertainment.outings.other` | 📝 | Other | Autre | أخرى | |
| `entertainment.sport_hobbies` | 🏋️ | *Sport & hobbies* | *Sport & passe-temps* | *الرياضة والهوايات* | |
| `entertainment.sport_hobbies.gym_sport` | 🏃 | Gym & sport | Salle de sport & sport | النادي الرياضي والرياضة | |
| `entertainment.sport_hobbies.games_hobbies` | 🎮 | Games & hobbies | Jeux & passe-temps | الألعاب والهوايات | |
| `entertainment.sport_hobbies.other` | 📝 | Other | Autre | أخرى | |
| `entertainment.subscriptions` | 📺 | *Subscriptions* | *Abonnements* | *الاشتراكات* | |
| `entertainment.subscriptions.streaming` | 🎞️ | Streaming | Streaming | خدمات البث | |
| `entertainment.subscriptions.apps_services` | 📲 | Apps & services | Applications & services | التطبيقات والخدمات | |
| `entertainment.subscriptions.other` | 📝 | Other | Autre | أخرى | |
| `entertainment.travel` | ✈️ | *Travel* | *Voyages* | *السفر* | |
| `entertainment.travel.tickets` | 🎫 | Tickets | Billets | التذاكر | |
| `entertainment.travel.accommodation` | 🏨 | Accommodation | Hébergement | الإقامة | |
| `entertainment.travel.activities` | 🗺️ | Activities | Activités | الأنشطة | |
| `entertainment.travel.other` | 📝 | Other | Autre | أخرى | |
| `celebrations` | 🎉 | **Celebrations & giving** | **Fêtes & dons** | **المناسبات والعطاء** | expense |
| `celebrations.muslim_holidays` | 🌙 | *Muslim holidays* | *Fêtes musulmanes* | *الأعياد الإسلامية* | |
| `celebrations.muslim_holidays.ramadan` |  | Ramadan | Ramadan | رمضان | |
| `celebrations.muslim_holidays.eid_al_fitr` |  | Eid al-Fitr | Aïd el-Fitr | عيد الفطر | |
| `celebrations.muslim_holidays.eid_al_adha` |  | Eid al-Adha | Aïd el-Kébir | عيد الأضحى | |
| `celebrations.muslim_holidays.other` | 📝 | Other | Autre | أخرى | |
| `celebrations.christian_holidays` | 🎄 | *Christian holidays* | *Fêtes chrétiennes* | *الأعياد المسيحية* | |
| `celebrations.christian_holidays.christmas` |  | Christmas | Noël | عيد الميلاد المجيد | |
| `celebrations.christian_holidays.easter` |  | Easter | Pâques | عيد الفصح | |
| `celebrations.christian_holidays.other` | 📝 | Other | Autre | أخرى | |
| `celebrations.jewish_holidays` | 🕎 | *Jewish holidays* | *Fêtes juives* | *الأعياد اليهودية* | |
| `celebrations.jewish_holidays.hanukkah` |  | Hanukkah | Hanoukka | حانوكا | |
| `celebrations.jewish_holidays.passover` |  | Passover | Pessa'h | عيد الفصح اليهودي | |
| `celebrations.jewish_holidays.rosh_hashanah` |  | Rosh Hashanah | Roch Hachana | رأس السنة العبرية | |
| `celebrations.jewish_holidays.other` | 📝 | Other | Autre | أخرى | |
| `celebrations.family_events` | 🎂 | *Family events* | *Événements familiaux* | *المناسبات العائلية* | |
| `celebrations.family_events.birthdays` | 🎂 | Birthdays | Anniversaires | أعياد الميلاد | |
| `celebrations.family_events.weddings` | 💍 | Weddings | Mariages | الأعراس | |
| `celebrations.family_events.new_year` | 🎆 | New Year | Nouvel An | رأس السنة | |
| `celebrations.family_events.other` | 📝 | Other | Autre | أخرى | |
| `celebrations.gifts_giving` | 🎁 | *Gifts & giving* | *Cadeaux & dons* | *الهدايا والتبرعات* | |
| `celebrations.gifts_giving.gifts_given` | 🎁 | Gifts given | Cadeaux offerts | الهدايا المقدّمة | |
| `celebrations.gifts_giving.charity` | 🤲 | Charity & donations | Dons & charité | الصدقات والتبرعات | |
| `celebrations.gifts_giving.family_help` | 👪 | Help to family | Aide à la famille | مساعدة العائلة | |
| `celebrations.gifts_giving.other` | 📝 | Other | Autre | أخرى | |
| `finance` | 🧾 | **Finance & admin** | **Finances & administratif** | **المالية والشؤون الإدارية** | expense |
| `finance.banking` | 🏦 | *Banking* | *Banque* | *البنك* | |
| `finance.banking.bank_fees` | 💳 | Bank fees | Frais bancaires | الرسوم البنكية | |
| `finance.banking.loan_repayment` | 💸 | Loan repayment | Remboursement de prêt | سداد القروض | |
| `finance.banking.other` | 📝 | Other | Autre | أخرى | |
| `finance.insurance` | 🛡️ | *Insurance* | *Assurances* | *التأمين* | |
| `finance.insurance.home` | 🏠 | Home | Habitation | تأمين المسكن | |
| `finance.insurance.car` | 🚗 | Car | Auto | تأمين السيارة | |
| `finance.insurance.health` | 🩺 | Health | Santé | التأمين الصحي | |
| `finance.insurance.life` | ❤️ | Life | Vie | التأمين على الحياة | |
| `finance.insurance.other` | 📝 | Other | Autre | أخرى | |
| `finance.taxes_paperwork` | 📄 | *Taxes & paperwork* | *Impôts & démarches* | *الضرائب والوثائق الإدارية* | |
| `finance.taxes_paperwork.taxes` | 🏛️ | Taxes | Impôts | الضرائب | |
| `finance.taxes_paperwork.official_fees` | 📑 | Official fees | Frais administratifs | الرسوم الإدارية | |
| `finance.taxes_paperwork.other` | 📝 | Other | Autre | أخرى | |
| `misc` | 🧺 | **Miscellaneous** | **Divers** | **متفرقات** | expense |
| `misc.tobacco_alcohol` | 🚬 | *Tobacco & alcohol* | *Tabac & alcool* | *التبغ والكحول* | |
| `misc.tobacco_alcohol.cigarettes` | 🚬 | Cigarettes & tobacco | Cigarettes & tabac | السجائر والتبغ | |
| `misc.tobacco_alcohol.vaping` | 💨 | Vaping | Vapotage | السجائر الإلكترونية | |
| `misc.tobacco_alcohol.shisha` | 🫖 | Shisha | Chicha | الشيشة | |
| `misc.tobacco_alcohol.alcohol` | 🍷 | Alcohol | Alcool | الكحول | |
| `misc.tobacco_alcohol.other` | 📝 | Other | Autre | أخرى | |
| `misc.other` | ❓ | *Other* | *Autre* | *أخرى* | |
| `misc.other.other` | 📝 | Other | Autre | أخرى | |
| `income` | 💼 | **Income** | **Revenus** | **الدخل** | income |
| `income.work` | 💼 | *Work* | *Travail* | *العمل* | |
| `income.work.salary` | 💰 | Salary | Salaire | الراتب | |
| `income.work.bonus` | 🎯 | Bonus | Prime | المكافأة | |
| `income.work.freelance` | 🧑‍💻 | Freelance & side income | Freelance & revenus annexes | العمل الحر والمداخيل الإضافية | |
| `income.work.other` | 📝 | Other | Autre | أخرى | |
| `income.assets` | 📈 | *Assets* | *Patrimoine* | *الممتلكات* | |
| `income.assets.rental_income` | 🏘️ | Rental income | Revenus locatifs | مداخيل الإيجار | |
| `income.assets.investment_income` | 📊 | Investment income | Revenus de placements | عائدات الاستثمار | |
| `income.assets.other` | 📝 | Other | Autre | أخرى | |
| `income.other_income` | 🤲 | *Other income* | *Autres revenus* | *مداخيل أخرى* | |
| `income.other_income.gifts_received` | 🎁 | Gifts received | Cadeaux reçus | الهدايا المستلمة | |
| `income.other_income.benefits` | 🏛️ | Benefits & allowances | Aides & allocations | المنح والإعانات | |
| `income.other_income.refunds` | ↩️ | Refunds | Remboursements | المبالغ المستردة | |
| `income.other_income.other` | 📝 | Other | Autre | أخرى | |
| `savings` | 🪙 | **Savings** | **Épargne** | **الادخار** | saving |
| `savings.reserves` | 🛟 | *Reserves* | *Réserves* | *الاحتياطي* | |
| `savings.reserves.emergency_fund` | 🆘 | Emergency fund | Fonds d'urgence | صندوق الطوارئ | |
| `savings.reserves.savings_goal` | 🎯 | Savings goal | Objectif d'épargne | هدف الادخار | |
| `savings.reserves.other` | 📝 | Other | Autre | أخرى | |
| `savings.long_term` | 📈 | *Long term* | *Long terme* | *ادخار طويل المدى* | |
| `savings.long_term.investments` | 📊 | Investments | Placements | الاستثمارات | |
| `savings.long_term.retirement` | 🧓 | Retirement | Retraite | التقاعد | |
| `savings.long_term.other` | 📝 | Other | Autre | أخرى | |
