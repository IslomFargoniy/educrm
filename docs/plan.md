# mensalc.uz — O'quv markaz boshqaruv tizimi: reja

> Holat: amalga oshirilgan (veb-tizim, Telegram bot, Android ilova). Sayt: https://mensalc.uz Stack: Laravel 13, Inertia v3 + React, Wayfinder, Fortify, Pest, MySQL.
> Interfeys tillari: o'zbekcha (lotin, asosiy), ruscha, inglizcha. Foydalanuvchi tilni o'zi tanlaydi va tanlov profilda saqlanadi.
> Brend: **Mensa Learning Center** — butun tizim va dizayn logoga moslanadi (§9).

## 1. Asosiy biznes qoidalar

### 1.1 O'quvchi to'lovi (kalendar oy, guruhning oylik dars soni)

- Har guruhda ikkita asosiy son bor:
    - **oylik narx** (masalan 600 000 so'm);
    - **oylik dars soni — N** (masalan 12; haftada 5 kun o'qiydigan guruh uchun 20, haftada 2 kun uchun 8).
- **1 dars narxi = oylik narx / N.** Masalan, 600 000 / 12 = 50 000.
- To'lov **kalendar oy** bo'yicha hisoblanadi: bir oyda bitta o'quvchidan bitta guruh uchun **ko'pi bilan N ta dars** pul olinadi. Masalan, N = 12 bo'lgan guruhda oyda 13–14 dars bo'lsa, 13- va 14-darslar bepul.
- O'quvchi oy o'rtasidan kelsa, faqat o'tilgan darslar hisoblanadi. Masalan, oyning 5-sanasidan kelib 12 ta darsga qatnashsa, to'liq to'laydi; 8 ta dars qolgan bo'lsa, 8/12 narxini to'laydi.
- Davomat oyda nechta dars bo'lsa, shuncha kun uchun qilinadi (cheklov faqat pulga tegishli).
- Davomat holatlari:

    | Holat            | Pul yechiladimi                         |
    | ---------------- | --------------------------------------- |
    | Keldi            | Ha                                      |
    | Sababsiz kelmadi | Ha                                      |
    | Sababli kelmadi  | **Yo'q** (N talik hisobga ham kirmaydi) |

- **Sinov darsi**: yangi o'quvchi guruhga "sinov" holatida qo'shiladi. Sinov darslari soni **har o'quvchi uchun** guruhga qo'shilayotganda kiritiladi.
    - Davomat odatdagidek belgilanadi, dars narxi esa **kutilayotgan** sifatida yoziladi: balansdan yechilmaydi va o'qituvchiga ulush yozilmaydi.
    - O'quvchi **davom ettirsa**, sinov darslari oddiy darsga aylanadi: balansdan yechiladi va o'sha oyning N talik hisobiga kiradi. O'qituvchi ulushi odatdagi qoidaga ko'ra, pul bilan qoplanganda yoziladi.
    - **Davom ettirmasa**, sinov darslari bekor qilinadi: pul olinmaydi, o'qituvchiga ulush yo'q.
- **Muzlatish**: o'quvchini guruhda ma'lum muddatga (masalan 2 hafta) muzlatish mumkin. Bu davrda darslar hisoblanmaydi va o'quvchi davomat ro'yxatida ko'rinmaydi. Muddat tugagach u avtomatik faollashadi. Muzlatishlar tarixi saqlanadi.
- **Narx o'zgarishi**: guruh narxi (yoki N) o'zgartirilsa, yangi qiymat **keyingi oyning 1-sanasidan** qo'llanadi. Joriy oy eski narxda yakunlanadi. Narxlar tarixi saqlanadi.
- **Bo'linish**: oylik narx doim N ga qoldiqsiz bo'linadi, shuning uchun yaxlitlash kerak emas. Summalar butun so'mda saqlanadi. Guruh narxi va chegirmadan keyingi narx kiritilganda N ga bo'linishi tekshiriladi. Natijasi bo'linmaydigan chegirma **qabul qilinmaydi** (masalan N = 12 va 600 000 uchun 60 000 so'm chegirma → 540 000 bo'ladi, 5 000 so'm chegirma → 595 000 bo'lmaydi).

### 1.2 Darslar jadvali

- Guruh kunlari, boshlanish va tugash vaqti, xonasi bo'yicha har oy darslar avtomatik yaratiladi.
- **Bayram kalendari**: admin bayram va dam olish kunlarini kiritadi (barcha filiallar uchun yoki bitta filial uchun). O'sha kunlardagi darslar avtomatik bekor qilinadi, pul olinmaydi.
- **O'qituvchi kelmasa**: o'rinbosar tayinlanadi (§1.4). O'rinbosar topilmasa, admin har safar o'zi tanlaydi:
    - darsni shunchaki **bekor qilish** (pul olinmaydi);
    - yoki bekor qilib, boshqa kunga **qo'shimcha dars** qo'yish (u odatdagi dars kabi N talik hisobga kiradi).
- **Xonalar**: har filialda xonalar ro'yxati bo'ladi. Guruh yaratish yoki jadvalni o'zgartirishda tekshiriladi: bir xonada yoki bir o'qituvchida bir vaqtda ikki guruh bo'lsa, **ogohlantirish** chiqadi.
- Har guruhda bitta o'qituvchi bo'ladi (assistent yo'q).

### 1.3 O'quvchi balansi (har guruh uchun alohida)

- Har bir **o'quvchi + guruh** juftligi (`enrollment`) alohida balansga ega.
- Dars o'tilganda dars narxi balansdan **yechiladi**. Balansda pul bo'lmasa, balans **minusga** tushadi (qarz).
- To'lov qabul qilinganda u shu guruh balansiga tushadi.
- **Balansni ko'chirish**: bir guruhdagi ortiqcha (musbat) pulni o'quvchining boshqa guruhiga o'tkazish mumkin. Bu ikkinchi guruh uchun to'lov kabi ishlaydi va o'sha guruhdagi qarzni yopadi.
- **Oy o'rtasida guruh almashtirish** (masalan, darajasi o'zgarib): bu "ko'chirish" sifatida belgilanadi.
    - Eski guruh yopiladi, qoldiq balans yangi guruhga ko'chadi.
    - Shu oy uchun cheklov **ikki guruh birgalikda** hisoblanadi. Masalan, ikkala guruhda N = 12 bo'lsa va eski guruhda 5 dars hisoblangan bo'lsa, yangisida shu oy ko'pi bilan 7 ta hisoblanadi.
    - N har xil bo'lsa, oyning ishlatilgan ulushi bo'yicha hisoblanadi: yangi guruhdagi chegara = N_yangi × (1 − eski guruhda hisoblangan darslar / N_eski), pastga yaxlitlanadi.
- **Qarz bilan chiqib ketish**: qarz tizimda saqlanadi va qarzdorlar ro'yxatida turadi. O'quvchi qaytib kelsa yoki to'lasa, qarz odatdagidek yopiladi va o'qituvchiga ulush yoziladi. Admin xohlasa qarzni sabab yozib **hisobdan chiqaradi**: balans nolga tushadi, o'qituvchiga ulush yozilmaydi, bu amal tarixda ko'rinadi.
- **Chegirma**: har bir `enrollment` uchun foiz yoki oylik summa ko'rinishida. Dars narxi = (oylik narx − chegirma) / N. Bir necha oyga oldindan to'lov uchun alohida chegirma yo'q: oldindan to'lov oddiy to'lov kabi balansga tushadi.
- **Qarzdorga cheklov yo'q**: qarzdor darsga kelaveradi, u faqat qarzdorlar ro'yxatida va eslatmalarda ko'rinadi.

### 1.4 O'qituvchi ulushi

- Har guruhda **standart foiz** bor (masalan 40%). Guruhdagi istalgan o'quvchi uchun **alohida foiz** belgilash mumkin (masalan 70%); belgilanmasa, guruh foizi olinadi.
- **Foiz o'zgarishi** keyingi darsdan kuchga kiradi. Har dars yozuvida o'sha paytdagi foiz saqlanadi, shuning uchun eski qarz keyin to'lansa ham ulush dars kunidagi foiz bo'yicha hisoblanadi.
- Ulush **darsni haqiqatda o'tgan** o'qituvchiga yoziladi. Asosiy o'qituvchi kelmay, o'rniga boshqasi dars o'tsa, o'sha dars ulushi **o'rinbosarga** yoziladi. O'rinbosar asosiy o'qituvchi bilan **bir xil foizni** oladi: o'quvchiga alohida foiz belgilangan bo'lsa o'sha, aks holda guruh foizi.
- **O'qituvchi balansi faqat o'quvchi puli bilan qoplangan darslardan oshadi:**
    - O'quvchi balansida pul bor → dars o'tilganda darhol ulush yoziladi.
    - Balans minusda → dars qarzga yoziladi, o'qituvchiga **hech narsa yozilmaydi**.
    - O'quvchi keyinroq pul to'lasa → pul **eng eski qoplanmagan darslardan boshlab** (FIFO) taqsimlanadi, har bir yopilgan dars ulushi **o'sha darsni o'tgan** o'qituvchiga yoziladi.
    - Qisman to'lovda ulush ham qisman bo'ladi.
- O'qituvchi **faqat foiz** oladi: qo'lda bonus yoki jarima yozish yo'q.
- **Foiz faqat butun son** (0–100), kasr foiz kiritib bo'lmaydi. Agar ulush baribir butun so'mga chiqmasa (masalan 41 650 × 33%), u pastga yaxlitlanadi va qoldiq markazda qoladi.
- O'qituvchi balans tarixida har bir kirim aniq ko'rinishi kerak: **o'quvchi, guruh, dars sanasi, dars summasi, foiz, ulush, to'lov sanasi** va "o'rinbosar sifatida" belgisi.

### 1.5 Kassa

- **Har to'lov usuliga alohida kassa** (naqd, karta, Click, Payme, bank o'tkazmasi), **har filial uchun** alohida. Har kassaning o'z qoldig'i bor.
- **To'lov qabul qilish**: kassir summani va kassani tanlab kiritadi. Click/Payme orqali kelgan to'lovlar ham boshida **qo'lda** kiritiladi; keyingi bosqichda avtomatik integratsiya qo'shiladi (§7, 11-bosqich).
- **Chek**: to'lov qabul qilingach chop etiladigan chek chiqariladi: termal printer (58/80 mm) va A4 formatida. Chekda markaz, filial, o'quvchi, guruh, oy, summa, to'lov usuli, kassir va to'lovdan keyingi balans bo'ladi. Chekni keyin qayta chop etish mumkin.
- **To'lovni bekor qilish**: kassir o'zi kiritgan to'lovni **o'sha kuni** bekor qila oladi; keyingi kunlarda faqat admin yoki superadmin. Bekor qilish teskari yozuv bilan bajariladi, sababi yoziladi.
- Kassadan chiqimlar:
    - **O'qituvchi oyligi**: o'qituvchi istalgan payt kassaga keladi, kassir summani kiritib beradi. Summa **o'qituvchi balansidan oshmaydi**.
    - **Xarajat**: kategoriya bilan (reklama, ijara, banner, remont, farrosh oyligi va h.k.; kategoriyalar admin tomonidan boshqariladi). Kassir kiritgan xarajat **darhol** kassadan chiqadi, tasdiq talab qilinmaydi; admin uni tarixda ko'radi.
    - **Foyda yechish**: superadmin yoki admin qoldiqni "foyda" deb belgilab yechib oladi.
    - **Pulni qaytarish**: o'quvchi ketsa, balansidagi ishlatilmagan pul **to'liq** qaytariladi (ushlab qolish yo'q).
    - **Kassalar orasida o'tkazma**: masalan naqd puldan bankka.
- Hech bir kassa qoldig'i minusga tushmaydi.
- **Bo'sh foyda** = kassalar jami − o'qituvchilar balanslari jami − o'quvchilarning oldindan to'lagan (hali ishlatilmagan) pullari. Foyda yechish oynasida shu raqam ko'rsatiladi. Bo'sh foydadan ortiq summa kiritilsa, **ogohlantirish** chiqadi ("bu pul o'qituvchilar va o'quvchilarga tegishli"), lekin tasdiqlansa yechishga ruxsat beriladi. Bu holat tarixda belgi bilan saqlanadi.
- Kunlik kassa yopish (smena) yo'q: kassa qoldig'i tarixda doim ko'rinib turadi.

### 1.6 Balans tarixi

Uch joyda bo'ladi: **kassa**, **o'qituvchi**, **o'quvchi**. Hammasida bir xil ustunlar ishlatiladi:

**Sana · Amal · Tafsilot · Kirim (+) · Chiqim (−) · Qoldiq**

- Kirim va chiqim alohida ustunda yoziladi (bitta ± ustun emas), shunda pul qayerga ketgani darhol ko'rinadi.
- **Qoldiq** — shu amaldan keyingi balans.
- Jadval tepasida tanlangan davr uchun xulosa turadi: davr boshidagi qoldiq, jami kirim, jami chiqim, davr oxiridagi qoldiq.
- Sana, amal turi va guruh bo'yicha filtr, Excel eksport.
- Qatorni bosganda batafsil oyna ochiladi: kim kiritgan, qaysi kassa, izoh, bog'liq yozuvlar.

Quyidagi namunalar §2 dagi misolga mos keladi (N = 12).

#### Kassa tarixi — "Naqd kassa (Chilonzor filiali)", noyabr 2026

| Xulosa            | Summa     |
| ----------------- | --------- |
| 01.11 dagi qoldiq | 1 000 000 |
| Jami kirim        | 600 000   |
| Jami chiqim       | 1 020 000 |
| 30.11 dagi qoldiq | 580 000   |

| Sana  | Amal              | Tafsilot                   | Kirim   | Chiqim  | Qoldiq    |
| ----- | ----------------- | -------------------------- | ------- | ------- | --------- |
| 01.11 | O'quvchi to'lovi  | Ali Valiyev — Ingliz-1     | 100 000 |         | 1 100 000 |
| 04.11 | Xarajat           | Reklama — Instagram        |         | 300 000 | 800 000   |
| 08.11 | Kassadan kassaga  | Click kassadan o'tkazildi  | 500 000 |         | 1 300 000 |
| 15.11 | O'qituvchi oyligi | Aziz Karimov               |         | 70 000  | 1 230 000 |
| 20.11 | Pul qaytarish     | Vali Toshev — Matematika-2 |         | 150 000 | 1 080 000 |
| 30.11 | Foyda yechildi    | Admin                      |         | 500 000 | 580 000   |

Kassa tarixida faqat shu kassadan haqiqatda kirgan va chiqqan pul ko'rinadi. Masalan, Ali 12.11 da Click orqali to'lagan bo'lsa, u to'lov "Click kassa" tarixida chiqadi.

#### O'qituvchi tarixi — "Aziz Karimov", noyabr 2026

| Xulosa                                       | Summa  |
| -------------------------------------------- | ------ |
| 01.11 dagi qoldiq                            | 0      |
| Jami ishlagan                                | 70 000 |
| Jami olgan                                   | 70 000 |
| 30.11 dagi qoldiq                            | 0      |
| **Kutilmoqda** (o'quvchilar hali to'lamagan) | 0      |

| Sana  | Amal         | Tafsilot                                          | Kirim  | Chiqim | Qoldiq |
| ----- | ------------ | ------------------------------------------------- | ------ | ------ | ------ |
| 03.11 | Dars ulushi  | Ingliz-1, 03.11 darsi — 1 o'quvchi                | 35 000 |        | 35 000 |
| 12.11 | Qarz yopildi | Ali Valiyev to'ladi → Ingliz-1, 07.11 darsi uchun | 35 000 |        | 70 000 |
| 15.11 | Oylik oldi   | Naqd kassa (Chilonzor)                            |        | 70 000 | 0      |

- **Dars ulushi** qatori bitta darsni bitta qatorda ko'rsatadi, nechta o'quvchi bo'lsa ham. Qatorni bosganda har o'quvchi alohida chiqadi:

    | O'quvchi    | Dars narxi | Foiz | Ulush  |
    | ----------- | ---------- | ---- | ------ |
    | Ali Valiyev | 50 000     | 70%  | 35 000 |

- **Qarz yopildi** qatori o'quvchi eski qarzini to'laganda paydo bo'ladi. Unda kim to'lagani va qaysi dars uchun ekani yoziladi.
- O'rinbosar sifatida o'tilgan dars "Dars ulushi (o'rinbosar)" deb yoziladi. Masalan, boshqa o'qituvchining tarixida: _05.11 — Dars ulushi (o'rinbosar) — Ingliz-1, 05.11 darsi, Aziz Karimov o'rniga — +35 000_.
- **Kutilmoqda** bloki tarixdan alohida turadi. Unda o'tilgan, lekin o'quvchi hali pul to'lamagan darslar ro'yxati bo'ladi. Bu pul balansga qo'shilmagan, faqat ma'lumot uchun ko'rsatiladi. Masalan, 07.11 dan 12.11 gacha unda _"Ali Valiyev — Ingliz-1, 07.11 darsi — 35 000"_ turgan.

#### O'quvchi tarixi — "Ali Valiyev · Ingliz-1", noyabr 2026

Har guruh alohida sahifa (tab) bo'ladi. Tepasida guruh balansi va oy hisoblagichi turadi: **Noyabr: 3 / 12 dars hisoblandi**.

| Xulosa             | Summa                             |
| ------------------ | --------------------------------- |
| 01.11 dagi qoldiq  | 0                                 |
| Jami to'langan     | 700 000                           |
| Jami darslar uchun | 150 000                           |
| Hozirgi qoldiq     | **+550 000** (oldindan to'langan) |

| Sana  | Amal   | Tafsilot                       | Kirim   | Chiqim | Qoldiq      |
| ----- | ------ | ------------------------------ | ------- | ------ | ----------- |
| 01.11 | To'lov | Naqd                           | 100 000 |        | 100 000     |
| 03.11 | Dars   | Keldi · noyabrdagi 1-dars      |         | 50 000 | 50 000      |
| 05.11 | Dars   | Keldi · 2-dars                 |         | 50 000 | 0           |
| 07.11 | Dars   | Sababsiz kelmadi · 3-dars      |         | 50 000 | **−50 000** |
| 10.11 | Dars   | Sababli kelmadi · hisoblanmadi |         | —      | −50 000     |
| 12.11 | To'lov | Click                          | 600 000 |        | 550 000     |

- Qoldiq minus bo'lsa qizil rangda "qarz", plus bo'lsa yashil rangda ko'rsatiladi.
- Oyda N ta dars hisoblangandan keyingi darslar ham ko'rinadi, lekin chiqim o'rniga "bepul (12 dan oshdi)" deb yoziladi.
- Balans ko'chirish ikki guruhda ham ko'rinadi: _"Matematika-2 ga ko'chirildi"_ (chiqim) va _"Ingliz-1 dan ko'chirildi"_ (kirim).
- Profilning yuqori qismida barcha guruhlar balansi yonma-yon ko'rsatiladi.

### 1.7 Lidlar (potentsial o'quvchilar)

- Qo'ng'iroq qilgan yoki yozilgan odamlar ro'yxati: ism, telefon, qiziqqan kurs, filial, **manba** (Instagram, Telegram, tanish, banner va h.k.), mas'ul xodim, keyingi aloqa sanasi, izohlar.
- Holatlar: yangi → bog'lanildi → sinovga yozildi → o'quvchi bo'ldi / rad etdi.
- "Sinovga yozish" tugmasi liddan o'quvchi yaratadi va uni guruhga **sinov** holatida qo'shadi (§1.1).
- Hisobot: manbalar bo'yicha lidlar soni va o'quvchiga aylanish foizi (reklama samaradorligini ko'rish uchun).

## 2. Ishlash namunasi

Guruh: oylik narx 600 000, N = 12, dars narxi 50 000, guruh foizi 40%. O'quvchi Ali uchun alohida foiz 70%.

| Sana  | Hodisa                                           | Ali balansi | O'qituvchi A                   | O'qituvchi B |
| ----- | ------------------------------------------------ | ----------- | ------------------------------ | ------------ |
| 01.11 | Ali 100 000 to'ladi (naqd kassa +100 000)        | +100 000    | 0                              | 0            |
| 03.11 | Dars (A), Ali keldi → −50 000, qoplangan         | +50 000     | +35 000                        | 0            |
| 05.11 | Dars (B o'rinbosar), Ali keldi → qoplangan       | 0           | 35 000                         | +35 000      |
| 07.11 | Dars (A), sababsiz kelmadi → qarz                | −50 000     | 35 000                         | 35 000       |
| 10.11 | Dars (A), sababli kelmadi → yechilmaydi          | −50 000     | 35 000                         | 35 000       |
| 12.11 | Ali 600 000 to'ladi → avval 07.11 qarzi yopiladi | +550 000    | **+35 000** (07.11 dars uchun) | 35 000       |
| 15.11 | A kassadan 70 000 oldi                           | 550 000     | 0                              | 35 000       |

Noyabrda Ali uchun yechilgan darslar soni 12 ga yetgach, qolgan darslar bepul bo'ladi.

## 3. Rollar va ruxsatlar (spatie/laravel-permission)

| Rol                  | Asosiy imkoniyatlar                                                                                                                                                                                                         |
| -------------------- | --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| **superadmin**       | Butun tizim: **barcha filiallar**, filial yaratish va o'chirish, filial adminlarini tayinlash, rollar va ruxsatlar, bayram kalendari, barcha filiallar bo'yicha umumiy hisobotlar, istalgan filial kassasidan foyda yechish |
| **admin**            | **O'z filiali** doirasida hamma narsa: foydalanuvchilar, foiz va narxlar, kassalar, xonalar, foyda yechish, eski to'lovlarni bekor qilish, hisobotlar                                                                       |
| **manager** (kassir) | O'quvchi, lid, guruh, to'lov qabul qilish (o'sha kuni bekor qilish), xarajat, o'qituvchiga oylik berish, qaytarish; o'z filiali doirasida. Foyda yecha olmaydi                                                              |
| **teacher**          | O'z guruhlari, davomat (dars kuni), o'z balansi va tarixi                                                                                                                                                                   |
| **parent** (ota-ona) | **Barcha farzandlari**: guruhlari, balansi, to'lovlari, davomati — bitta loginda                                                                                                                                            |
| **student**          | O'z guruhlari, balansi, to'lovlari, davomati                                                                                                                                                                                |

- **Davomat usuli**: o'qituvchi ro'yxatdan qo'lda belgilaydi (QR yoki karta yo'q).
- **O'qituvchi nimani ko'radi**: har biri alohida ruxsat, admin rol yoki o'qituvchi bo'yicha yoqib-o'chiradi: o'quvchi va ota-ona telefonlari (`students.view_phone`), qarzdorligi (`students.view_debt`), to'lovlari va balansi (`students.view_payments`). Hammasi o'chirilsa, o'qituvchi faqat ism va davomatni ko'radi.
- **Davomat huquqi**: o'qituvchi davomatni faqat **dars kuni** belgilaydi va o'zgartiradi. Keyingi kunlarda faqat `attendance.edit_past` ruxsati bor xodim (admin, menejer) o'zgartira oladi. Har bir o'zgarish kim va qachon qilgani bilan saqlanadi.
- **Login**: hamma (xodim, o'qituvchi, ota-ona, o'quvchi) **telefon raqam + parol** bilan kiradi (Fortify `username` = phone, +998 formatida, unikal). Email ixtiyoriy. Parolni admin beradi, unutilgan parolni boshida **admin tiklaydi**. SMS xizmati hali tanlanmagan; u ulangach, parolni SMS kod orqali tiklash qo'shiladi.
- Ruxsatlar sahifa va amal bo'yicha: `students.view`, `students.create`, `students.update`, `leads.*`, `groups.*`, `rooms.*`, `holidays.manage`, `attendance.mark`, `attendance.edit_past`, `payments.create`, `payments.reverse_today`, `payments.reverse`, `expenses.*`, `payouts.create`, `cashboxes.view`, `cashboxes.transfer`, `profit.withdraw`, `refunds.create`, `debts.writeoff`, `reports.view`, `users.manage`, `roles.manage`, `settings.manage` va h.k.
- Superadmin panelida rollarga ruxsatlarni belgilash sahifasi bo'ladi.
- Ruxsatlar backend'da (Policy + `can` middleware) va frontend'da (menyu/tugmalarni yashirish) tekshiriladi. Ruxsatlar ro'yxati Inertia shared props orqali uzatiladi.
- **Filial doirasi**: admin, menejer va o'qituvchi faqat o'ziga biriktirilgan filial(lar) ma'lumotlarini ko'radi. O'quvchining **bitta profili** bor va u turli filiallardagi guruhlarda o'qishi mumkin: har filial xodimi uni ko'radi, lekin faqat o'z filiali guruhlari, balanslari va to'lovlarini. To'lov guruh qaysi filialda bo'lsa, o'sha filial kassasiga tushadi. Superadmin hamma filialni ko'radi: yuqori panelda filial tanlagich bo'ladi ("Barcha filiallar" yoki bitta filial).
- Superadmin `Gate::before` orqali barcha ruxsatlarga ega bo'ladi. Uning rolini faqat boshqa superadmin bera oladi, tizimdagi oxirgi superadminni o'chirib bo'lmaydi.

## 4. Ma'lumotlar modeli

Barcha pul summalari `bigint`, butun so'mda.

| Jadval                | Asosiy ustunlar                                                                                                                                                                                          |
| --------------------- | -------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `branches`            | name, address, phone                                                                                                                                                                                     |
| `branch_user`         | branch_id, user_id                                                                                                                                                                                       |
| `users`               | + phone (unikal), locale, status; spatie jadvallari (roles, permissions…)                                                                                                                                |
| `rooms`               | branch_id, name, capacity                                                                                                                                                                                |
| `holidays`            | date, branch_id (null → barcha filiallar), name                                                                                                                                                          |
| `students`            | full_name, phone, birth_date, user_id (o'quvchi logini, ixtiyoriy), lead_id, status, note — filialga bog'lanmaydi, filial guruh orqali aniqlanadi                                                        |
| `guardian_student`    | user_id (ota-ona), student_id, relation (ota / ona / boshqa)                                                                                                                                             |
| `leads`               | branch_id, full_name, phone, source, interested_course, status, assigned_to, next_contact_at, student_id, note                                                                                           |
| `lead_notes`          | lead_id, user_id, text, created_at                                                                                                                                                                       |
| `groups`              | branch_id, name, subject, teacher_id, room_id, teacher_percent, days, start_time, end_time, start_date, end_date, status                                                                                 |
| `group_prices`        | group_id, monthly_price, lessons_per_month (N), effective_from (oy boshi), created_by                                                                                                                    |
| `enrollments`         | student_id, group_id, joined_at, left_at, status (sinov / faol / muzlatilgan / ko'chirildi / chiqdi), trial_lessons, transferred_from_id, discount_type, discount_value, **balance** (kesh)              |
| `enrollment_percents` | enrollment_id, teacher_percent, effective_from (sana-vaqt) — o'quvchiga alohida foiz va uning tarixi                                                                                                     |
| `enrollment_freezes`  | enrollment_id, from_date, to_date, reason, created_by                                                                                                                                                    |
| `lessons`             | group_id, date, start_time, room_id, teacher_id (haqiqatda o'tgan), is_substitute, is_makeup, status (rejada / o'tildi / bekor qilindi), cancel_reason                                                   |
| `attendances`         | lesson_id, enrollment_id, status (keldi / sababsiz / sababli), note, marked_by                                                                                                                           |
| `attendance_audits`   | attendance_id, old_status, new_status, changed_by, changed_at                                                                                                                                            |
| `lesson_charges`      | enrollment_id, lesson_id, attendance_id, month (YYYY-MM), seq_in_month (1–N), amount, covered_amount, teacher_id, teacher_percent (dars kunidagi), status (kutilmoqda-sinov / faol / bekor), reversed_at |
| `charge_coverages`    | lesson_charge_id, source (payment / transfer), source_id, amount, teacher_amount, teacher_id, covered_at                                                                                                 |
| `payments`            | enrollment_id, cashbox_id, amount, paid_at, received_by, receipt_no, note, reversed_at, reversed_by, reverse_reason                                                                                      |
| `cashboxes`           | branch_id, name, method (naqd / karta / click / payme / bank), **balance** (kesh), is_active                                                                                                             |
| `expense_categories`  | name, is_active                                                                                                                                                                                          |
| `transactions`        | type, occurred_at, created_by, note, reverses_id, meta (json)                                                                                                                                            |
| `ledger_entries`      | transaction_id, account_type (cashbox / teacher / enrollment / expense / owner), account_id, amount (±), balance_after                                                                                   |
| `notification_logs`   | channel (sms / telegram), recipient, template, payload, status, error                                                                                                                                    |

### Buxgalteriya yondashuvi (ledger)

- Har bir pul harakati bitta `transaction` va bir nechta `ledger_entries` qatoridan iborat. **Balans tarixi aynan shu qatorlardan olinadi.**
- Kesh qilingan `balance` ustunlari bir xil DB tranzaksiyasi ichida `lockForUpdate` bilan yangilanadi. `balance_after` tarixda saqlanadi.
- **O'chirish yo'q**: xato yozuv teskari (storno) tranzaksiya bilan bekor qilinadi. Tarixda ikkala yozuv ham ko'rinadi.
- Tranzaksiya turlari: `student_payment`, `payment_reversal`, `lesson_charge`, `charge_reversal`, `teacher_earning`, `teacher_payout`, `expense`, `owner_withdrawal`, `cashbox_transfer`, `balance_transfer`, `refund`, `debt_writeoff`, `opening_balance`, `adjustment`.

## 5. Asosiy jarayonlar (servislar)

1. **Dars jadvali**: guruh kunlari va vaqtidan oylik `lessons` avtomatik yaratiladi (rejalashtirilgan job). Bayram kunlari o'tkazib yuboriladi. Qo'shimcha darsni qo'lda qo'shish yoki darsni bekor qilish mumkin. Bekor qilingan dars uchun pul yechilmaydi.
2. **Jadval to'qnashuvi**: guruh yaratish yoki o'zgartirishda, qo'shimcha dars qo'yishda bir xona yoki bir o'qituvchida vaqt kesishsa ogohlantirish chiqadi.
3. **O'rinbosar**: darsda `teacher_id` boshqa o'qituvchiga almashtiriladi (`is_substitute = true`). O'rinbosar topilmasa, admin darsni bekor qiladi yoki bekor qilib qo'shimcha dars (`is_makeup = true`) qo'yadi.
4. **Davomat saqlash → `BillingService::chargeLesson`**:
    - Har o'quvchi uchun: holat "sababli" bo'lsa o'tkazib yuboriladi. Shu oyda yechilgan darslar soni N dan kam bo'lsa (ko'chirilgan o'quvchida — ikki guruh birgalikda, §1.3) → `lesson_charge` yaratiladi, aks holda bepul.
    - Dars narxi o'sha oyning narxi va N idan, foiz esa dars kunidagi foizdan olinadi.
    - Balansdagi bo'sh pul darhol qoplashga ishlatiladi (`CoverageService`), qoplangan qism uchun o'qituvchi ulushi yoziladi.
    - O'quvchi muzlatilgan bo'lsa, u darsda ko'rinmaydi va hisob qilinmaydi.
    - O'quvchi sinovda bo'lsa, `lesson_charge` "kutilmoqda-sinov" holatida yaratiladi: balansga ham, o'qituvchiga ham ta'sir qilmaydi.
    - **Sinovdan keyingi qaror**: "davom etadi" tanlansa, kutilayotgan yozuvlar faol bo'ladi va dars sanasi tartibida balansdan yechiladi, keyin qoplash ishga tushadi. "Davom etmaydi" tanlansa, ular bekor qilinadi.
5. **Davomatni o'zgartirish**: eski `lesson_charge` va unga bog'liq ulush teskari yozuv bilan bekor qilinadi, keyin qayta hisoblanadi. O'qituvchi pulni allaqachon olgan bo'lsa, uning balansi minusga tushishi mumkin; bu tarixda ko'rinadi.
6. **To'lov qabul qilish → `PaymentService`**: kassa +, `enrollment` balansi +, so'ng `CoverageService` eng eski qoplanmagan darslarni FIFO tartibida yopadi va o'qituvchi ulushlarini yozadi. Chek raqami beriladi.
7. **To'lovni bekor qilish**: to'lov, unga bog'liq qoplashlar va o'qituvchi ulushlari teskari yozuv bilan bekor qilinadi; darslar yana qarz bo'lib qoladi.
8. **Balans ko'chirish va guruh almashtirish**: A guruhning bo'sh (musbat) puli → B guruhga, keyin B da qoplash ishga tushadi. Guruh almashtirishda eski enrollment "ko'chirildi" holatiga o'tadi va yangisi `transferred_from_id` bilan bog'lanadi.
9. **O'qituvchi oyligi**: summa ≤ o'qituvchi balansi va ≤ kassa qoldig'i.
10. **Xarajat / foyda yechish / qaytarish / kassalararo o'tkazma**: kassa qoldig'i yetarliligi tekshiriladi.
11. **Parallel amallar**: barcha pul amallari `DB::transaction` ichida, kerakli qatorlar qulflangan holda bajariladi. Bir xil to'lov ikki marta yozilmasligi uchun idempotency kaliti ishlatiladi.

## 6. Sahifalar

- **Dashboard**: bugungi tushum, kassalar qoldig'i, qarzdorlar soni va summasi, bo'sh foyda, yangi lidlar, oylik grafik.
- **Lidlar**: ro'yxat (filtr: holat, manba, mas'ul, keyingi aloqa sanasi) → lid kartasi: izohlar tarixi, sinovga yozish.
- **O'quvchilar**: ro'yxat (filtr: guruh, qarzdor, holat) → profil: guruhlar, har guruh balansi, to'lov qabul qilish va chek, balans ko'chirish, guruh almashtirish, chegirma, alohida foiz, muzlatish, qaytarish, qarzni hisobdan chiqarish, ota-onalar, tarix, davomat.
- **Guruhlar**: ro'yxat → guruh: o'quvchilar va ularning foizlari, jadval, narx va N tarixi, darslar, davomat jurnali (oylik jadval ko'rinishida), o'rinbosar tayinlash, darsni bekor qilish yoki qo'shimcha dars.
- **Jadval**: filial bo'yicha haftalik jadval (xonalar × vaqt), to'qnashuvlar belgilanadi.
- **Davomat**: o'qituvchi uchun bugungi darslar → bir bosishda belgilash.
- **O'qituvchilar**: ro'yxat → profil: balans, batafsil tarix, "kutilmoqda" bloki, oylik berish, guruhlari.
- **Kassa**: kassalar va qoldiqlari → kassa tarixi; xarajat, foyda yechish, o'tkazma.
- **Hisobotlar**: davr bo'yicha kirim/chiqim, xarajatlar kategoriya bo'yicha, o'qituvchilar ulushi, qarzdorlar, guruh rentabelligi, lid manbalari.
- **Sozlamalar**: filiallar, xonalar, bayram kalendari, foydalanuvchilar, rollar va ruxsatlar, xarajat kategoriyalari, kassalar, chek shabloni, SMS/Telegram.
- **Shaxsiy kabinetlar**: o'qituvchi, ota-ona (barcha farzandlar) va o'quvchi uchun cheklangan ko'rinish.

## 7. Bosqichlar

| #   | Bosqich                        | Natija                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                   |
| --- | ------------------------------ | -------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| 0   | Poydevor                       | Brend va dizayn tizimi (§9), telefon orqali login, uch tilli interfeys (uz / ru / en), spatie/laravel-permission, rollar va ruxsatlar seederi, filiallar, xonalar, foydalanuvchilarni boshqarish, Money helper, Ledger servisi va testlari                                                                                                                                                                                                                                                               |
| 1   | O'quvchi va guruhlar           | CRUD, ota-onalar, enrollment, narx va N tarixi, foiz va chegirma, dars jadvali generatori, bayram kalendari, jadval to'qnashuvi                                                                                                                                                                                                                                                                                                                                                                          |
| 2   | Darslar va davomat             | Davomat sahifasi (dars kuni qoidasi, o'zgarishlar tarixi), o'rinbosar, darsni bekor qilish va qo'shimcha dars, sinov darsi, muzlatish                                                                                                                                                                                                                                                                                                                                                                    |
| 3   | Hisob-kitob yadrosi            | N dars cheklovi, sababli holat, guruh almashtirishda umumiy cheklov, qoplash (FIFO), o'qituvchi ulushi; **to'liq Pest testlari** (§2 namunasi test sifatida)                                                                                                                                                                                                                                                                                                                                             |
| 4   | Kassa                          | Kassalar, to'lov qabul qilish va chek chop etish, to'lovni bekor qilish, xarajat, oylik berish, foyda yechish, o'tkazma, qaytarish, balans ko'chirish, qarzni hisobdan chiqarish                                                                                                                                                                                                                                                                                                                         |
| 5   | Balans tarixlari va hisobotlar | Kassa, o'qituvchi va o'quvchi tarixi, filtr, eksport, dashboard                                                                                                                                                                                                                                                                                                                                                                                                                                          |
| 6   | Lidlar                         | Lidlar ro'yxati, holatlar, izohlar, sinovga yozish, manbalar hisoboti                                                                                                                                                                                                                                                                                                                                                                                                                                    |
| 7   | Shaxsiy kabinetlar             | O'qituvchi, ota-ona va o'quvchi portallari                                                                                                                                                                                                                                                                                                                                                                                                                                                               |
| 8   | Xabarnomalar va Telegram       | Telegram bot: ota-ona telefon raqamini ulashib bog'lanadi, xabarlar oladi va botdan farzandining balansi va davomatini so'raydi. **Telegram Mini App** — o'qituvchi barcha ishlarini (davomat, o'z guruhlari, balansi va tarixi) shu yerda ham qila oladi. SMS (provayder keyin tanlanadi; drayver orqali ulanadigan qilib yoziladi). Hammasi navbat (queue) orqali: to'lov qabul qilindi; qarz eslatmasi — balans minusga tushgan kuni, har oy boshida va qarz yopilmaguncha har hafta; dars qoldirildi |
| 9   | Excel import                   | Shablon fayl, o'quvchilar, guruhlar, enrollment va boshlang'ich balanslar (qarz yoki oldindan to'lov `opening_balance` sifatida). Yuklashdan oldin tekshiruv va xatolar ro'yxati ko'rsatiladi, tasdiqlangandan keyin saqlanadi                                                                                                                                                                                                                                                                           |
| 10  | Deploy                         | Deploy skripti, queue worker va scheduler (cron), zaxira nusxa — FastPanel zaxira rejasi orqali                                                                                                                                                                                                                                                                                                                                                                                                          |
| 11  | Click / Payme integratsiyasi   | Ota-ona kabinetidan yoki havola orqali to'lov; to'lov avtomatik tegishli kassa va o'quvchi balansiga tushadi (merchant shartnomasi kerak)                                                                                                                                                                                                                                                                                                                                                                |
| 12  | API va Android ilova           | Laravel Sanctum asosidagi REST API (`/api/v1`), so'ng **Kotlin**da Android ilova — **admin / menejer** va **o'qituvchi** uchun. Sayt tayyor bo'lgach boshlanadi                                                                                                                                                                                                                                                                                                                                          |

**Mijoz ilovalari**: barcha rollar uchun veb-sayt; u **PWA** sifatida telefonga ilova kabi o'rnatiladi (0-bosqichda manifest va ikonkalar). O'qituvchi uchun qo'shimcha Telegram Mini App (8-bosqich), admin/menejer va o'qituvchi uchun Android ilova (12-bosqich). Shu sababli biznes mantiq boshidanoq controller'larda emas, **Action/Service klasslarda** yoziladi: veb (Inertia), Telegram Mini App va API bir xil kodni chaqiradi, hisob-kitob qoidalari takrorlanmaydi.

Har bosqich alohida PR sifatida, testlar bilan topshiriladi. Hisob-kitob (3–4 bosqich) eng muhim qism: unda har bir chekka holat uchun test yoziladi.

## 8. Qabul qilingan qarorlar

| Mavzu                      | Qaror                                                                                                                                        |
| -------------------------- | -------------------------------------------------------------------------------------------------------------------------------------------- |
| To'lov davri               | Kalendar oy, oyiga ko'pi bilan N dars (§1.1)                                                                                                 |
| N (oylik dars soni)        | Har guruhga alohida belgilanadi (§1.1)                                                                                                       |
| Qoldirilgan dars           | Sababsiz — hisoblanadi, sababli — hisoblanmaydi (§1.1)                                                                                       |
| Bo'linish va chegirma      | Narx N ga bo'linadi; bo'linmaydigan chegirma qabul qilinmaydi (§1.1)                                                                         |
| Sinov darsi                | Soni o'quvchiga qarab; davom ettirsa pul olinadi, aks holda yo'q (§1.1)                                                                      |
| Muzlatish                  | Bor (§1.1)                                                                                                                                   |
| Narx o'zgarishi            | Keyingi oydan (§1.1)                                                                                                                         |
| Bayramlar                  | Bayram kalendari, darslar avtomatik bekor (§1.2)                                                                                             |
| O'rinbosar topilmasa       | Admin har safar hal qiladi: bekor yoki qo'shimcha dars (§1.2)                                                                                |
| Xonalar                    | Bor, to'qnashuv tekshiriladi (§1.2)                                                                                                          |
| Assistent o'qituvchi       | Yo'q (§1.2)                                                                                                                                  |
| Balans                     | Har guruhga alohida, ko'chirish mumkin (§1.3)                                                                                                |
| Guruh almashtirish         | Shu oy uchun ikki guruh birgalikda N (§1.3)                                                                                                  |
| Qarz bilan ketish          | Qarz saqlanadi, admin hisobdan chiqarishi mumkin (§1.3)                                                                                      |
| O'qituvchi ulushi          | Pul bilan qoplangan darsdan, darsni o'tganga (§1.4)                                                                                          |
| O'rinbosar foizi           | O'quvchi yoki guruh foizi (§1.4)                                                                                                             |
| Foiz o'zgarishi            | Keyingi darsdan (§1.4)                                                                                                                       |
| Bonus / jarima             | Yo'q, faqat foiz (§1.4)                                                                                                                      |
| Kassalar                   | Har to'lov usuli va filialga alohida (§1.5)                                                                                                  |
| Click / Payme              | Avval qo'lda, keyin integratsiya (§1.5, 11-bosqich)                                                                                          |
| Chek                       | Chop etiladi: termal va A4 (§1.5)                                                                                                            |
| To'lovni bekor qilish      | Kassir o'sha kuni, keyin admin (§1.5)                                                                                                        |
| Xarajat                    | Tasdiqsiz, darhol (§1.5)                                                                                                                     |
| O'qituvchi oyligi          | Kassir beradi, balans doirasida (§1.5)                                                                                                       |
| Foyda yechish              | Bo'sh foydadan ortig'i ogohlantirish va tasdiq bilan (§1.5)                                                                                  |
| Kassa yopish (smena)       | Yo'q (§1.5)                                                                                                                                  |
| Lidlar (CRM)               | Bor (§1.7)                                                                                                                                   |
| Rollar                     | superadmin, admin, menejer/kassir, o'qituvchi, ota-ona, o'quvchi (§3)                                                                        |
| Ota-ona                    | Bitta login, barcha farzandlar (§3)                                                                                                          |
| Davomat huquqi             | O'qituvchi dars kuni, keyin admin yoki menejer (§3)                                                                                          |
| Login                      | Telefon + parol (§3)                                                                                                                         |
| Til                        | O'zbekcha, ruscha, inglizcha                                                                                                                 |
| Baholar / uy vazifasi      | Yo'q                                                                                                                                         |
| Pulni qaytarish            | To'liq, ushlab qolishsiz (§1.5)                                                                                                              |
| Oldindan to'lov chegirmasi | Yo'q (§1.3)                                                                                                                                  |
| Qarzdorga cheklov          | Yo'q, faqat ro'yxat va eslatma (§1.3)                                                                                                        |
| Qarz eslatmasi             | Minusga tushganda, har oy boshida, har hafta (8-bosqich)                                                                                     |
| Davomat usuli              | O'qituvchi qo'lda (§3)                                                                                                                       |
| O'qituvchi ko'rishi        | Telefon, qarz, to'lovlar — har biri alohida ruxsat (§3)                                                                                      |
| Ko'p filial                | O'quvchining bitta profili, istalgan filialda o'qiydi (§3)                                                                                   |
| Shartnoma / hujjatlar      | Kerak emas, faqat chek                                                                                                                       |
| Foiz                       | Faqat butun son; kasr ulush pastga yaxlitlanadi (§1.4)                                                                                       |
| Mobil                      | Sayt + PWA hamma uchun; o'qituvchi uchun Telegram Mini App; admin/menejer va o'qituvchi uchun Kotlin Android ilova sayt tayyor bo'lgach (§7) |
| Telegram bot               | Ota-ona: xabarlar + balans va davomatni so'rash (8-bosqich)                                                                                  |
| Zaxira nusxa               | FastPanel zaxira rejasi (10-bosqich)                                                                                                         |
| SMS xizmati                | Keyin tanlanadi (8-bosqich)                                                                                                                  |
| Import                     | Excel'dan (9-bosqich)                                                                                                                        |
| Brend                      | Mensa logosi va ranglari, butun dizayn shunga moslanadi (§9)                                                                                 |

## 9. Brend va dizayn

Butun tizim **Mensa Learning Center** logosiga moslanadi.

### 9.1 Ranglar

Logodan olingan (taxminiy qiymatlar; aniq HEX kodlari asl fayldan olinadi):

| Token                       | Rang                            | Qayerda ishlatiladi                                                                                                                                      |
| --------------------------- | ------------------------------- | -------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `primary`                   | To'q ko'k (navy), ~`#0B0F7E`    | Asosiy tugmalar, faol menyu, sarlavhalar, login sahifasi foni, sidebar                                                                                   |
| `accent`                    | To'q sariq (orange), ~`#F7941D` | Diqqat talab qiladigan joylar: asosiy amal tugmasi ("To'lov qabul qilish"), belgi va badge'lar, grafikdagi ajratilgan qiymat. Kam va o'rinli ishlatiladi |
| `background` / `foreground` | Oq / to'q ko'k-kulrang          | Sahifa foni va matn                                                                                                                                      |
| `success` / `danger`        | Yashil / qizil                  | Faqat pul holati uchun: musbat balans, qarz (§1.6)                                                                                                       |

- Ranglar Tailwind v4 va shadcn/ui CSS o'zgaruvchilari (`--primary`, `--accent` …) orqali bir joyda belgilanadi, komponentlar ichiga to'g'ridan-to'g'ri rang yozilmaydi.
- **Qorong'i rejim** ham bo'ladi: fon to'q navy, primary yorqinroq ko'k, accent o'sha orange.
- Rang kontrasti WCAG AA darajasida tekshiriladi (ayniqsa orange fon ustidagi matn).

### 9.2 Logo ishlatilishi

| Joy                                              | Variant                                                          |
| ------------------------------------------------ | ---------------------------------------------------------------- |
| Login, parolni tiklash sahifalari                | To'liq logo (belgi + "Mensa" + "Learning center"), navy fonda oq |
| Sidebar (ochiq)                                  | Belgi + "Mensa" yozuvi                                           |
| Sidebar (yig'ilgan), favicon, mobil ikonka (PWA) | Faqat belgi (kitob/qalqon + orange romb)                         |
| Chek (termal printer)                            | Oq-qora, soddalashtirilgan belgi + "Mensa Learning Center"       |
| Chek (A4), hisobotlar eksporti (PDF/Excel)       | To'liq rangli logo                                               |
| SMS / Telegram xabarlar                          | Imzo: "Mensa Learning Center"                                    |

### 9.3 Shrift va uslub

- Logodagi yozuvga yaqin, qalin geometrik sans-serif shrift; **lotin va kirill** harflarini qo'llab-quvvatlashi shart (ruscha interfeys uchun). Variantlar: Manrope, Onest, Inter — logo bilan solishtirib tanlanadi.
- Burchaklar biroz yumaloq, soyalar yengil; kartalar oq fonda, sarlavha qismi navy.
- Ilova nomi hamma joyda: **Mensa** (to'liq: Mensa Learning Center); `APP_NAME` ham shunga o'zgartiriladi.
- Hozirgi starter-kit sahifasi (welcome) Mensa uchun oddiy bosh sahifa yoki to'g'ridan-to'g'ri login sahifasi bilan almashtiriladi.

### 9.4 Kerakli fayllar

- Logoning asl fayli: **SVG** (yoki yuqori sifatli PNG, shaffof fon bilan) — to'liq va faqat belgi variantlari.
- Aniq rang kodlari (HEX), agar brendbukda bo'lsa.
