# mensalc.uz — O'quv markaz boshqaruv tizimi: reja

> Holat: reja (kod yozilmagan). Stack: Laravel 13, Inertia v3 + React, Wayfinder, Fortify, Pest, MySQL.

## 1. Asosiy biznes qoidalar

### 1.1 O'quvchi to'lovi (kalendar oy, 12 dars cheklovi)
- Har guruhning **oylik narxi** bor (masalan 600 000 so'm). **1 dars narxi = oylik narx / 12**.
- To'lov **kalendar oy** bo'yicha hisoblanadi: bir oyda bitta o'quvchidan bitta guruh uchun **ko'pi bilan 12 ta dars** pul olinadi. Oyda 13–14 dars bo'lsa, 13- va 14-darslar bepul.
- O'quvchi oy o'rtasidan kelsa, faqat o'tilgan darslar hisoblanadi. Masalan, oyning 5-sanasidan kelib 12 ta darsga qatnashsa, to'liq to'laydi; 8 ta dars qolgan bo'lsa, 8/12 narxini to'laydi.
- Davomat oyda nechta dars bo'lsa, shuncha kun uchun qilinadi (cheklov faqat pulga tegishli).
- Davomat holatlari:
  | Holat | Pul yechiladimi |
  |---|---|
  | Keldi | Ha |
  | Sababsiz kelmadi | Ha |
  | Sababli kelmadi | **Yo'q** (12 talik hisobga ham kirmaydi) |
- **Narx o'zgarishi**: guruh narxi o'zgartirilsa, yangi narx **keyingi oyning 1-sanasidan** qo'llanadi. Joriy oy eski narxda yakunlanadi. Narxlar tarixi saqlanadi.
- **Bo'linish**: kurs narxi doim 12 ga qoldiqsiz bo'linadi, shuning uchun yaxlitlash kerak emas. Summalar butun so'mda saqlanadi. Guruh narxi va chegirmadan keyingi narx kiritilganda 12 ga bo'linishi validatsiya qilinadi.

### 1.2 O'quvchi balansi (har guruh uchun alohida)
- Har bir **o'quvchi + guruh** juftligi (`enrollment`) alohida balansga ega.
- Dars o'tilganda dars narxi balansdan **yechiladi**. Balansda pul bo'lmasa, balans **minusga** tushadi (qarz).
- To'lov qabul qilinganda u shu guruh balansiga tushadi.
- **Balansni ko'chirish**: bir guruhdagi ortiqcha (musbat) pulni o'quvchining boshqa guruhiga o'tkazish mumkin. Bu ikkinchi guruh uchun to'lov kabi ishlaydi va o'sha guruhdagi qarzni yopadi.
- **Qarz bilan chiqib ketish**: qarz tizimda saqlanadi va qarzdorlar ro'yxatida turadi. O'quvchi qaytib kelsa yoki to'lasa, qarz odatdagidek yopiladi va o'qituvchiga ulush yoziladi. Admin xohlasa qarzni sabab yozib **hisobdan chiqaradi**: balans nolga tushadi, o'qituvchiga ulush yozilmaydi, bu amal tarixda ko'rinadi.
- **Chegirma**: har bir `enrollment` uchun foiz yoki oylik summa ko'rinishida. Dars narxi = (oylik narx − chegirma) / 12.

### 1.3 O'qituvchi ulushi
- Har guruhda **standart foiz** bor (masalan 40%). Guruhdagi istalgan o'quvchi uchun **alohida foiz** belgilash mumkin (masalan 70%); belgilanmasa, guruh foizi olinadi.
- Ulush **darsni haqiqatda o'tgan** o'qituvchiga yoziladi. Asosiy o'qituvchi kelmay, o'rniga boshqasi dars o'tsa, o'sha dars ulushi **o'rinbosarga** yoziladi. O'rinbosar asosiy o'qituvchi bilan **bir xil foizni** oladi: o'quvchiga alohida foiz belgilangan bo'lsa o'sha, aks holda guruh foizi.
- **O'qituvchi balansi faqat o'quvchi puli bilan qoplangan darslardan oshadi:**
  - O'quvchi balansida pul bor → dars o'tilganda darhol ulush yoziladi.
  - Balans minusda → dars qarzga yoziladi, o'qituvchiga **hech narsa yozilmaydi**.
  - O'quvchi keyinroq pul to'lasa → pul **eng eski qoplanmagan darslardan boshlab** (FIFO) taqsimlanadi, har bir yopilgan dars ulushi **o'sha darsni o'tgan** o'qituvchiga yoziladi.
  - Qisman to'lovda ulush ham qisman bo'ladi.
- O'qituvchi balans tarixida har bir kirim aniq ko'rinishi kerak: **o'quvchi, guruh, dars sanasi, dars summasi, foiz, ulush, to'lov sanasi** va "o'rinbosar sifatida" belgisi.

### 1.4 Kassa
- **Har to'lov usuliga alohida kassa** (naqd, karta, Click, Payme, bank o'tkazmasi), **har filial uchun** alohida. Har kassaning o'z qoldig'i bor.
- Kassadan chiqimlar:
  - **O'qituvchi oyligi**: o'qituvchi istalgan payt, faqat **balansidagi summa doirasida** oladi.
  - **Xarajat**: kategoriya bilan (reklama, ijara, banner, remont, farrosh oyligi va h.k.; kategoriyalar admin tomonidan boshqariladi).
  - **Foyda yechish**: admin qoldiqni "foyda" deb belgilab yechib oladi.
  - **Pulni qaytarish**: o'quvchi ketsa, balansidagi ortiqcha pul qaytariladi.
  - **Kassalar orasida o'tkazma**: masalan naqd puldan bankka.
- Hech bir kassa qoldig'i minusga tushmaydi.
- **Bo'sh foyda** = kassalar jami − o'qituvchilar balanslari jami − o'quvchilarning oldindan to'lagan (hali ishlatilmagan) pullari. Foyda yechish oynasida shu raqam ko'rsatiladi. Superadmin yoki admin bo'sh foydadan ortiq summa kiritsa, **ogohlantirish** chiqadi ("bu pul o'qituvchilar va o'quvchilarga tegishli"), lekin tasdiqlasa yechishga ruxsat beriladi. Bu holat tarixda belgi bilan saqlanadi.

### 1.5 Balans tarixi
Uch joyda bo'ladi: **kassa**, **o'qituvchi**, **o'quvchi**. Hammasida bir xil ustunlar ishlatiladi:

**Sana · Amal · Tafsilot · Kirim (+) · Chiqim (−) · Qoldiq**

- Kirim va chiqim alohida ustunda yoziladi (bitta ± ustun emas), shunda pul qayerga ketgani darhol ko'rinadi.
- **Qoldiq** — shu amaldan keyingi balans.
- Jadval tepasida tanlangan davr uchun xulosa turadi: davr boshidagi qoldiq, jami kirim, jami chiqim, davr oxiridagi qoldiq.
- Sana, amal turi va guruh bo'yicha filtr, Excel eksport.
- Qatorni bosganda batafsil oyna ochiladi: kim kiritgan, qaysi kassa, izoh, bog'liq yozuvlar.

Quyidagi namunalar §2 dagi misolga mos keladi.

#### Kassa tarixi — "Naqd kassa (Chilonzor filiali)", noyabr 2026

| Xulosa | Summa |
|---|---|
| 01.11 dagi qoldiq | 1 000 000 |
| Jami kirim | 600 000 |
| Jami chiqim | 1 020 000 |
| 30.11 dagi qoldiq | 580 000 |

| Sana | Amal | Tafsilot | Kirim | Chiqim | Qoldiq |
|---|---|---|---|---|---|
| 01.11 | O'quvchi to'lovi | Ali Valiyev — Ingliz-1 | 100 000 | | 1 100 000 |
| 04.11 | Xarajat | Reklama — Instagram | | 300 000 | 800 000 |
| 08.11 | Kassadan kassaga | Click kassadan o'tkazildi | 500 000 | | 1 300 000 |
| 15.11 | O'qituvchi oyligi | Aziz Karimov | | 70 000 | 1 230 000 |
| 20.11 | Pul qaytarish | Vali Toshev — Matematika-2 | | 150 000 | 1 080 000 |
| 30.11 | Foyda yechildi | Admin | | 500 000 | 580 000 |

Kassa tarixida faqat shu kassadan haqiqatda kirgan va chiqqan pul ko'rinadi. Masalan, Ali 12.11 da Click orqali to'lagan bo'lsa, u to'lov "Click kassa" tarixida chiqadi.

#### O'qituvchi tarixi — "Aziz Karimov", noyabr 2026

| Xulosa | Summa |
|---|---|
| 01.11 dagi qoldiq | 0 |
| Jami ishlagan | 70 000 |
| Jami olgan | 70 000 |
| 30.11 dagi qoldiq | 0 |
| **Kutilmoqda** (o'quvchilar hali to'lamagan) | 0 |

| Sana | Amal | Tafsilot | Kirim | Chiqim | Qoldiq |
|---|---|---|---|---|---|
| 03.11 | Dars ulushi | Ingliz-1, 03.11 darsi — 1 o'quvchi | 35 000 | | 35 000 |
| 12.11 | Qarz yopildi | Ali Valiyev to'ladi → Ingliz-1, 07.11 darsi uchun | 35 000 | | 70 000 |
| 15.11 | Oylik oldi | Naqd kassa (Chilonzor) | | 70 000 | 0 |

- **Dars ulushi** qatori bitta darsni bitta qatorda ko'rsatadi, nechta o'quvchi bo'lsa ham. Qatorni bosganda har o'quvchi alohida chiqadi:

  | O'quvchi | Dars narxi | Foiz | Ulush |
  |---|---|---|---|
  | Ali Valiyev | 50 000 | 70% | 35 000 |

- **Qarz yopildi** qatori o'quvchi eski qarzini to'laganda paydo bo'ladi. Unda kim to'lagani va qaysi dars uchun ekani yoziladi.
- O'rinbosar sifatida o'tilgan dars "Dars ulushi (o'rinbosar)" deb yoziladi. Masalan, boshqa o'qituvchining tarixida: *05.11 — Dars ulushi (o'rinbosar) — Ingliz-1, 05.11 darsi, Aziz Karimov o'rniga — +35 000*.
- **Kutilmoqda** bloki tarixdan alohida turadi. Unda o'tilgan, lekin o'quvchi hali pul to'lamagan darslar ro'yxati bo'ladi. Bu pul balansga qo'shilmagan, faqat ma'lumot uchun ko'rsatiladi. Masalan, 07.11 dan 12.11 gacha unda *"Ali Valiyev — Ingliz-1, 07.11 darsi — 35 000"* turgan.

#### O'quvchi tarixi — "Ali Valiyev · Ingliz-1", noyabr 2026

Har guruh alohida sahifa (tab) bo'ladi. Tepasida guruh balansi va oy hisoblagichi turadi: **Noyabr: 3 / 12 dars hisoblandi**.

| Xulosa | Summa |
|---|---|
| 01.11 dagi qoldiq | 0 |
| Jami to'langan | 700 000 |
| Jami darslar uchun | 150 000 |
| Hozirgi qoldiq | **+550 000** (oldindan to'langan) |

| Sana | Amal | Tafsilot | Kirim | Chiqim | Qoldiq |
|---|---|---|---|---|---|
| 01.11 | To'lov | Naqd | 100 000 | | 100 000 |
| 03.11 | Dars | Keldi · noyabrdagi 1-dars | | 50 000 | 50 000 |
| 05.11 | Dars | Keldi · 2-dars | | 50 000 | 0 |
| 07.11 | Dars | Sababsiz kelmadi · 3-dars | | 50 000 | **−50 000** |
| 10.11 | Dars | Sababli kelmadi · hisoblanmadi | | — | −50 000 |
| 12.11 | To'lov | Click | 600 000 | | 550 000 |

- Qoldiq minus bo'lsa qizil rangda "qarz", plus bo'lsa yashil rangda ko'rsatiladi.
- Oyda 12 ta dars hisoblangandan keyingi darslar ham ko'rinadi, lekin chiqim o'rniga "bepul (12 dan oshdi)" deb yoziladi.
- Balans ko'chirish ikki guruhda ham ko'rinadi: *"Matematika-2 ga ko'chirildi"* (chiqim) va *"Ingliz-1 dan ko'chirildi"* (kirim).
- Profilning yuqori qismida barcha guruhlar balansi yonma-yon ko'rsatiladi.

## 2. Ishlash namunasi

Guruh: oylik narx 600 000, dars narxi 50 000, guruh foizi 40%. O'quvchi Ali uchun alohida foiz 70%.

| Sana | Hodisa | Ali balansi | O'qituvchi A | O'qituvchi B |
|---|---|---|---|---|
| 01.11 | Ali 100 000 to'ladi (naqd kassa +100 000) | +100 000 | 0 | 0 |
| 03.11 | Dars (A), Ali keldi → −50 000, qoplangan | +50 000 | +35 000 | 0 |
| 05.11 | Dars (B o'rinbosar), Ali keldi → qoplangan | 0 | 35 000 | +35 000 |
| 07.11 | Dars (A), sababsiz kelmadi → qarz | −50 000 | 35 000 | 35 000 |
| 10.11 | Dars (A), sababli kelmadi → yechilmaydi | −50 000 | 35 000 | 35 000 |
| 12.11 | Ali 600 000 to'ladi → avval 07.11 qarzi yopiladi | +550 000 | **+35 000** (07.11 dars uchun) | 35 000 |
| 15.11 | A kassadan 70 000 oldi | 550 000 | 0 | 35 000 |

Noyabrda Ali uchun yechilgan darslar soni 12 ga yetgach, qolgan darslar bepul bo'ladi.

## 3. Rollar va ruxsatlar (spatie/laravel-permission)

| Rol | Asosiy imkoniyatlar |
|---|---|
| **superadmin** | Butun tizim: **barcha filiallar**, filial yaratish va o'chirish, filial adminlarini tayinlash, rollar va ruxsatlar, barcha filiallar bo'yicha umumiy hisobotlar, istalgan filial kassasidan foyda yechish |
| **admin** | **O'z filiali** doirasida hamma narsa: foydalanuvchilar, foiz va narxlar, kassalar, foyda yechish, hisobotlar |
| **manager** (kassir) | O'quvchi, guruh, to'lov qabul qilish, xarajat, o'qituvchiga oylik berish, qaytarish; o'z filiali doirasida. Foyda yecha olmaydi |
| **teacher** | O'z guruhlari, davomat, o'z balansi va tarixi |
| **student** | O'z guruhlari, balansi, to'lovlari, davomati (ota-ona ham shu login bilan) |

- Ruxsatlar sahifa va amal bo'yicha: `students.view`, `students.create`, `students.update`, `groups.*`, `attendance.mark`, `payments.create`, `payments.reverse`, `expenses.*`, `payouts.create`, `cashboxes.view`, `cashboxes.transfer`, `profit.withdraw`, `refunds.create`, `reports.view`, `users.manage`, `roles.manage`, `settings.manage` va h.k.
- Superadmin panelida rollarga ruxsatlarni belgilash sahifasi bo'ladi.
- Ruxsatlar backend'da (Policy + `can` middleware) va frontend'da (menyu/tugmalarni yashirish) tekshiriladi. Ruxsatlar ro'yxati Inertia shared props orqali uzatiladi.
- **Filial doirasi**: admin, menejer va o'qituvchi faqat o'ziga biriktirilgan filial(lar) ma'lumotlarini ko'radi. Superadmin hamma filialni ko'radi: yuqori panelda filial tanlagich bo'ladi ("Barcha filiallar" yoki bitta filial).
- Superadmin `Gate::before` orqali barcha ruxsatlarga ega bo'ladi. Uning rolini faqat boshqa superadmin bera oladi, tizimdagi oxirgi superadminni o'chirib bo'lmaydi.

## 4. Ma'lumotlar modeli

Barcha pul summalari `bigint`, butun so'mda.

| Jadval | Asosiy ustunlar |
|---|---|
| `branches` | name, address, phone |
| `branch_user` | branch_id, user_id |
| `users` | + phone, status; spatie jadvallari (roles, permissions…) |
| `students` | full_name, phone, parent_phone, birth_date, user_id (login uchun, ixtiyoriy), branch_id, status, note |
| `groups` | branch_id, name, subject, teacher_id, monthly_price, teacher_percent, days (dush/chor/jum), start_time, room, start_date, end_date, status |
| `group_prices` | group_id, monthly_price, effective_from (oy boshi), created_by |
| `enrollments` | student_id, group_id, joined_at, left_at, status, teacher_percent (null → guruhniki), discount_type, discount_value, **balance** (kesh) |
| `lessons` | group_id, date, teacher_id (haqiqatda o'tgan), is_substitute, status (rejada / o'tildi / bekor qilindi), topic |
| `attendances` | lesson_id, enrollment_id, status (keldi / sababsiz / sababli), note, marked_by |
| `lesson_charges` | enrollment_id, lesson_id, attendance_id, month (YYYY-MM), seq_in_month (1–12), amount, covered_amount, teacher_id, teacher_percent, reversed_at |
| `charge_coverages` | lesson_charge_id, source (payment / transfer), source_id, amount, teacher_amount, teacher_id, covered_at |
| `payments` | enrollment_id, cashbox_id, amount, paid_at, received_by, note, reversed_at |
| `cashboxes` | branch_id, name, method (naqd / karta / click / payme / bank), **balance** (kesh), is_active |
| `expense_categories` | name, is_active |
| `transactions` | type, occurred_at, created_by, note, reverses_id, meta (json) |
| `ledger_entries` | transaction_id, account_type (cashbox / teacher / enrollment / expense / owner), account_id, amount (±), balance_after |
| `notification_logs` | channel (sms / telegram), recipient, template, payload, status, error |

### Buxgalteriya yondashuvi (ledger)
- Har bir pul harakati bitta `transaction` va bir nechta `ledger_entries` qatoridan iborat. **Balans tarixi aynan shu qatorlardan olinadi.**
- Kesh qilingan `balance` ustunlari bir xil DB tranzaksiyasi ichida `lockForUpdate` bilan yangilanadi. `balance_after` tarixda saqlanadi.
- **O'chirish yo'q**: xato yozuv teskari (storno) tranzaksiya bilan bekor qilinadi. Tarixda ikkala yozuv ham ko'rinadi.
- Tranzaksiya turlari: `student_payment`, `lesson_charge`, `charge_reversal`, `teacher_earning`, `teacher_payout`, `expense`, `owner_withdrawal`, `cashbox_transfer`, `balance_transfer`, `refund`, `debt_writeoff`, `opening_balance`, `adjustment`.

## 5. Asosiy jarayonlar (servislar)

1. **Dars jadvali**: guruh kunlari va vaqtidan oylik `lessons` avtomatik yaratiladi (rejalashtirilgan job). Qo'shimcha darsni qo'lda qo'shish yoki darsni bekor qilish mumkin. Bekor qilingan dars uchun pul yechilmaydi.
2. **O'rinbosar**: darsda `teacher_id` boshqa o'qituvchiga almashtiriladi (`is_substitute = true`).
3. **Davomat saqlash → `BillingService::chargeLesson`**:
   - Har o'quvchi uchun: holat "sababli" bo'lsa o'tkazib yuboriladi. Shu oyda yechilgan darslar soni 12 dan kam bo'lsa → `lesson_charge` yaratiladi, aks holda bepul.
   - Balansdagi bo'sh pul darhol qoplashga ishlatiladi (`CoverageService`), qoplangan qism uchun o'qituvchi ulushi yoziladi.
4. **Davomatni o'zgartirish**: eski `lesson_charge` va unga bog'liq ulush teskari yozuv bilan bekor qilinadi, keyin qayta hisoblanadi. O'qituvchi pulni allaqachon olgan bo'lsa, uning balansi minusga tushishi mumkin; bu tarixda ko'rinadi.
5. **To'lov qabul qilish → `PaymentService`**: kassa +, `enrollment` balansi +, so'ng `CoverageService` eng eski qoplanmagan darslarni FIFO tartibida yopadi va o'qituvchi ulushlarini yozadi.
6. **Balans ko'chirish**: A guruhning bo'sh (musbat) puli → B guruhga, keyin B da qoplash ishga tushadi.
7. **O'qituvchi oyligi**: summa ≤ o'qituvchi balansi va ≤ kassa qoldig'i.
8. **Xarajat / foyda yechish / qaytarish / kassalararo o'tkazma**: kassa qoldig'i yetarliligi tekshiriladi.
9. **Parallel amallar**: barcha pul amallari `DB::transaction` ichida, kerakli qatorlar qulflangan holda bajariladi. Bir xil to'lov ikki marta yozilmasligi uchun idempotency kaliti ishlatiladi.

## 6. Sahifalar

- **Dashboard**: bugungi tushum, kassalar qoldig'i, qarzdorlar soni va summasi, bo'sh foyda, oylik grafik.
- **O'quvchilar**: ro'yxat (filtr: guruh, qarzdor, holat) → profil: guruhlar, har guruh balansi, to'lov qabul qilish, balans ko'chirish, chegirma, qaytarish, tarix, davomat.
- **Guruhlar**: ro'yxat → guruh: o'quvchilar va ularning foizlari, jadval, darslar, davomat jurnali (oylik jadval ko'rinishida), o'rinbosar tayinlash.
- **Davomat**: o'qituvchi uchun bugungi darslar → bir bosishda belgilash.
- **O'qituvchilar**: ro'yxat → profil: balans, batafsil tarix, oylik berish, guruhlari.
- **Kassa**: kassalar va qoldiqlari → kassa tarixi; xarajat, foyda yechish, o'tkazma.
- **Hisobotlar**: davr bo'yicha kirim/chiqim, xarajatlar kategoriya bo'yicha, o'qituvchilar ulushi, qarzdorlar, guruh rentabelligi.
- **Sozlamalar**: filiallar, foydalanuvchilar, rollar va ruxsatlar, xarajat kategoriyalari, kassalar, SMS/Telegram.
- **Shaxsiy kabinetlar**: o'qituvchi va o'quvchi uchun cheklangan ko'rinish.

## 7. Bosqichlar

| # | Bosqich | Natija |
|---|---|---|
| 0 | Poydevor | spatie/laravel-permission, rollar va ruxsatlar seederi, filiallar, foydalanuvchilarni boshqarish, Money helper, Ledger servisi va testlari |
| 1 | O'quvchi va guruhlar | CRUD, enrollment, foiz va chegirma, dars jadvali generatori |
| 2 | Darslar va davomat | Davomat sahifasi, o'rinbosar, darsni bekor qilish |
| 3 | Hisob-kitob yadrosi | 12 dars cheklovi, sababli holat, qoplash (FIFO), o'qituvchi ulushi; **to'liq Pest testlari** (§2 namunasi test sifatida) |
| 4 | Kassa | Kassalar, to'lov qabul qilish, xarajat, oylik berish, foyda yechish, o'tkazma, qaytarish, balans ko'chirish, storno |
| 5 | Balans tarixlari va hisobotlar | Kassa, o'qituvchi va o'quvchi tarixi, filtr, eksport, dashboard |
| 6 | Shaxsiy kabinetlar | O'qituvchi va o'quvchi portallari |
| 7 | Xabarnomalar | SMS (masalan Eskiz.uz) va Telegram bot: to'lov qabul qilindi, qarz eslatmasi (rejalashtirilgan), dars qoldirildi; navbat (queue) orqali |
| 8 | Excel import | Shablon fayl, o'quvchilar, guruhlar, enrollment va boshlang'ich balanslar (qarz yoki oldindan to'lov `opening_balance` sifatida). Yuklashdan oldin tekshiruv va xatolar ro'yxati ko'rsatiladi, tasdiqlangandan keyin saqlanadi |
| 9 | Deploy | Deploy skripti, queue worker va scheduler (cron), zaxira nusxa |

Har bosqich alohida PR sifatida, testlar bilan topshiriladi. Hisob-kitob (3–4 bosqich) eng muhim qism: unda har bir chekka holat uchun test yoziladi.

## 8. Qabul qilingan qarorlar

1. **O'rinbosar foizi**: o'rinbosar o'sha o'quvchining (yoki guruhning) foizini oladi (§1.3).
2. **Foyda yechish**: bo'sh foydadan ortig'ini yechish ogohlantirish va tasdiq bilan mumkin (§1.4).
3. **Narx o'zgarishi**: yangi narx keyingi oydan qo'llanadi (§1.1).
4. **Qarz bilan chiqib ketish**: qarz saqlanadi, admin uni hisobdan chiqarishi mumkin (§1.2).
5. **Import**: eski ma'lumotlar Excel'dan yuklanadi (§7, 8-bosqich).
