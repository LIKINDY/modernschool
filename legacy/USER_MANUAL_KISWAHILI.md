# MWONGOZO WA MTUMIAJI WA MFUMO WA LIKINDY DIGITAL MANAGEMENT SYSTEM

**Toleo:** 1.0  
**Tarehe:** 1 Julai 2026  
**Lugha:** Kiswahili  
**Mwandishi:** GitHub Copilot kwa niaba ya Likindy Digital Solution

---

## 1. Utangulizi

Mfumo wa Likindy Digital Management System ni mfumo wa shule unaotumika kusimamia wanafunzi, walimu, alama za mitihani, ripoti, malipo, SMS, taarifa za shule, na taarifa za akaunti za watumiaji.

Mwongozo huu umeandaliwa ili kumsaidia mtumiaji mpya au aliyepo kuelewa namna ya kutumia mfumo kwa usahihi.

### 1.1 Malengo ya Mfumo
- Kusajili na kusimamia wanafunzi
- Kusajili na kusimamia walimu
- Kuingiza na kusahihisha matokeo
- Kutengeneza ripoti za wanafunzi na madarasa
- Kusimamia malipo na taarifa za fedha
- Kutuma SMS kwa wazazi/wafanyakazi
- Kusimamia taarifa za shule na mipangilio ya mfumo
- Kufuatilia shughuli za mfumo kupitia logs

### 1.2 Aina za Watumiaji
- Admin / Administrator
- Teacher / Mwalimu
- Accountant / Mhasibu
- Parent / Student

---

## 2. Jinsi ya Kuingia Kwenye Mfumo

### Hatua za kuingia
1. Fungua ukurasa wa login.
2. Weka username au email.
3. Weka password.
4. Chagua portal sahihi:
   - Administrator
   - Accountant
   - Teacher
   - Parent/Student
5. Bonyeza **Sign In Account**.

### Tahadhari
- Chagua role sahihi kulingana na account yako.
- Ukichagua role isiyo sahihi, mfumo utakukataa.

[WEKA PICTURE HAPA: Login Page]

---

## 3. Dashboard Kuu

Dashboard hutegemea aina ya mtumiaji.

### 3.1 Admin Dashboard
Admin anaona:
- Idadi ya wanafunzi
- Idadi ya walimu
- Review New
- SMS Sent
- Recent history
- Menyu ya usimamizi wa mfumo

Majukumu ya admin ni pamoja na:
- Kusimamia shule
- Kusajili watumiaji
- Kufuatilia logs
- Kuhariri mipangilio
- Kusimamia matokeo na ripoti

[WEKA PICTURE HAPA: Admin Dashboard]

### 3.2 Teacher Dashboard
Mwalimu anaona:
- Darasa/darasa alizopewa
- Masomo aliyopewa
- Kitufe cha kuingiza matokeo
- Attendance kama ni class teacher
- Security / password
- Logout

[WEKA PICTURE HAPA: Teacher Dashboard]

### 3.3 Student/Parent Dashboard
Mzazi au mwanafunzi anaona:
- Taarifa binafsi
- Darasa na stream
- Academic year
- Attendance history
- Results / report card

[WEKA PICTURE HAPA: Student Dashboard]

---

## 4. Usimamizi wa Mwalimu

### 4.1 Kusajili Mwalimu Mpya
Admin anatumia ukurasa wa ku-register teacher.

Hatua:
1. Fungua **Teachers**.
2. Bonyeza **Register New Teacher**.
3. Jaza taarifa binafsi.
4. Jaza elimu na maelezo ya kazi.
5. Chagua **Teaching Level**.
6. Chagua **Assigned Classes & Streams**.
7. Chagua masomo anayofundisha.
8. Bonyeza **Complete Registration**.

[WEKA PICTURE HAPA: Add Teacher Page]

### 4.2 Kuhariri Taarifa za Mwalimu
Hatua:
1. Fungua orodha ya walimu.
2. Bonyeza **Edit Info**.
3. Rekebisha taarifa zinazohitajika.
4. Hifadhi mabadiliko.

[WEKA PICTURE HAPA: Edit Teacher Page]

### 4.3 Kuchagua Teaching Level
Mfumo huu unatumia level ya mwalimu ili kumpeleka kwenye ukurasa sahihi wa kuingiza matokeo.

Teaching level zinazotumika:
- Nursery
- Primary
- O-Level
- A-Level

Mfano:
- Mwalimu wa Primary ataingia kwenye page ya Primary marks.
- Mwalimu wa O-Level ataingia kwenye page ya O-Level marks.

---

## 5. Kuingiza Matokeo

### 5.1 Primary Marks Entry
Mwalimu wa primary hutumia ukurasa wa:
- Primary Result Entry

Vipengele vikuu:
- Kuchagua subject
- Kuchagua class
- Kuchagua stream
- Kuchagua exam type
- Kuingiza marks za wanafunzi
- Auto-save
- Save All Marks

[WEKA PICTURE HAPA: Primary Marks Entry Page]

### 5.2 Nursery Marks Entry
Mwalimu wa nursery hutumia ukurasa wa:
- Nursery Results Entry

Vipengele:
- Subject
- Class
- Stream
- Exam type
- CA / Monthly / Exam
- Auto-save
- Save all

[WEKA PICTURE HAPA: Nursery Marks Entry Page]

### 5.3 O-Level Marks Entry
Mwalimu wa O-Level hutumia ukurasa wa:
- O-Level Results Portal

Vipengele:
- Subject
- Class
- Stream
- Exam type
- Year
- Auto-save
- Save All Results

[WEKA PICTURE HAPA: O-Level Marks Entry Page]

### 5.4 A-Level Marks Entry
Mwalimu wa A-Level hutumia ukurasa wa:
- A-Level Marks Entry

Vipengele:
- Class
- Combination
- Subject
- Term
- Year
- Keyboard navigation (Enter key)
- Auto-save
- Submit & Save All

[WEKA PICTURE HAPA: A-Level Marks Entry Page]

### 5.5 Mfumo wa Lock ya Matokeo
Mfumo una utaratibu wa kufunga alama pale completion inapofika 100%.

Maelezo:
- Teacher akimaliza kuingiza marks zote, mfumo unaweza kufunga entry.
- Teacher ataomba ruhusa ya kufungua tena.
- Admin ata-approve request na kufungua kwa muda.
- Admin hapati kizuizi cha lock kama teacher.

[WEKA PICTURE HAPA: Locked Entry / Request Unlock]

---

## 6. Ripoti za Matokeo

Mfumo una aina kadhaa za ripoti:
- Single student report
- Class report
- Stream report
- Broadsheet
- Final report
- Subject summary
- Detailed class report
- Export Excel/PDF style reports

### Ukurasa muhimu
- [result.php](result.php)
- [student_results.php](student_results.php)
- [primary_results.php](primary_results.php)
- [olevel_result.php](olevel_result.php)
- [bulk_reports.php](bulk_reports.php)

[WEKA PICTURE HAPA: Results Dashboard]

### Jinsi ya kuona matokeo ya mwanafunzi
1. Fungua student results au report page.
2. Tafuta mwanafunzi kwa jina au ID.
3. Chagua mwaka, term, au class ikiwa inahitajika.
4. Bonyeza view/report.

---

## 7. Usimamizi wa Wanafunzi

### 7.1 Kusajili Mwanafunzi Mpya
Ukurasa muhimu:
- Students
- Register Student

Hatua:
1. Fungua Students.
2. Bonyeza **Register New Student**.
3. Jaza taarifa za mwanafunzi.
4. Chagua class, stream, year, term.
5. Hifadhi.

[WEKA PICTURE HAPA: Register Student Modal/Page]

### 7.2 Import Students kwa Excel
Hatua:
1. Fungua import page.
2. Pakia file ya Excel/CSV.
3. Hakiki data.
4. Import mfumo.

[WEKA PICTURE HAPA: Import Students Page]

### 7.3 Kuhariri / Kufuta Mwanafunzi
Kutoka kwenye orodha ya wanafunzi unaweza:
- Edit info
- Upgrade student
- Delete student
- Generate ID

[WEKA PICTURE HAPA: Students List Page]

---

## 8. Usimamizi wa Walimu

Kwenye [teachers.php](teachers.php) admin anaweza:
- Kuona walimu wote
- Ku-edit taarifa
- Kufuta teacher
- Kufungua profile ya teacher

[WEKA PICTURE HAPA: Teachers List Page]

---

## 9. SMS Center

Ukurasa muhimu:
- [send_sms.php](send_sms.php)

Mfumo wa SMS unaruhusu:
- Kutuma ujumbe kwa parents
- Kutuma kwa staff
- Kuchagua delivery mode
- Kutumia API mode
- Kutumia Phone SMS mode
- Kuandika WhatsApp group link kama reference

### Hatua za kutuma SMS
1. Fungua SMS Center.
2. Chagua kundi: Parents au Staff.
3. Andika ujumbe.
4. Chagua delivery mode.
5. Kama ni API, jaza API endpoint na key.
6. Bonyeza send/confirm.

[WEKA PICTURE HAPA: Send SMS Page]

### Tahadhari
- SMS API lazima iwe imeunganishwa na provider halisi ili itume moja kwa moja.
- Phone SMS mode hufungua app ya SMS kwenye simu.

---

## 10. Mipangilio ya Shule

Ukurasa muhimu:
- [school_settings.php](school_settings.php)

Mambo yanayoweza kurekebishwa:
- Jina la shule
- Logo
- Namba ya simu
- P.O Box
- Anuani
- Slogan
- Headmaster / Principal

[WEKA PICTURE HAPA: School Settings Page]

Hatua:
1. Fungua School Settings.
2. Rekebisha taarifa.
3. Hifadhi mabadiliko.

---

## 11. Usimamizi wa Fedha na Malipo

Mfumo una sehemu za fedha kama:
- [Accountant.php](Accountant.php)
- [fee_settings.php](fee_settings.php)
- [fee_structure.php](fee_structure.php)
- [make_payment.php](make_payment.php)
- [payment_list.php](payment_list.php)
- [finance_report.php](finance_report.php)

Majukumu:
- Kuongeza malipo
- Kuona malipo yaliyofanyika
- Kuchapisha receipts
- Kufuata fee structure

[WEKA PICTURE HAPA: Accountant / Finance Page]

---

## 12. Attendance

Ukurasa muhimu:
- [attendance.php](attendance.php)
- [teacher_attendance.php](teacher_attendance.php)
- [view_attendance_history.php](view_attendance_history.php)
- [save_student_attendance.php](save_student_attendance.php)

Hatua:
1. Fungua attendance.
2. Chagua class/stream.
3. Weka mahudhurio.
4. Hifadhi.

[WEKA PICTURE HAPA: Attendance Page]

---

## 13. Logs na Ufuatiliaji wa Mfumo

Ukurasa muhimu:
- [system_logs.php](system_logs.php)
- [activity_logger.php](activity_logger.php)

Kazi ya logs:
- Kuonyesha login attempts
- Kuonyesha logout
- Kuonyesha SMS activity
- Kuonyesha shughuli za admin
- Kufuta log moja moja au zote

[WEKA PICTURE HAPA: System Logs Page]

---

## 14. Ripoti za Kina na Sheets

Mfumo una kurasa za ripoti kama:
- Broadsheet
- Marksheet
- Final report
- Subject summary
- Class stream reports
- Export Excel

Kurasa muhimu:
- [broadsheet.php](broadsheet.php)
- [broadsheet_primary.php](broadsheet_primary.php)
- [broadsheet_olevel.php](broadsheet_olevel.php)
- [report_single_student.php](report_single_student.php)
- [report_class_stream.php](report_class_stream.php)

[WEKA PICTURE HAPA: Broadsheet / Report Page]

---

## 15. Kazi za Msingi za Admin

Admin anaweza:
- Kusajili walimu na wanafunzi
- Kusimamia matokeo
- Kutuma SMS
- Kuangalia logs
- Kusimamia settings
- Kusimamia comments/reviews
- Kuangalia requests za edit

Kurasa muhimu:
- [admin_dashboard.php](admin_dashboard.php)
- [admin_profile.php](admin_profile.php)
- [manage_admins.php](manage_admins.php)
- [manage_accountants.php](manage_accountants.php)
- [manage_comments.php](manage_comments.php)
- [admin_marks_edit_requests.php](admin_marks_edit_requests.php)

---

## 16. Kazi za Msingi za Teacher

Teacher anaweza:
- Kuingiza matokeo kulingana na level yake
- Ku-request unlock kama marks zimefungwa
- Kuona madarasa/masomo aliyopewa
- Kubadilisha password
- Kuangalia attendance kama class teacher

Kurasa muhimu:
- [teacher_dashboard.php](teacher_dashboard.php)
- [exam_marks_router.php](exam_marks_router.php)
- [primary_enter_result.php](primary_enter_result.php)
- [nursery_add_marks.php](nursery_add_marks.php)
- [olevel_enter_result.php](olevel_enter_result.php)
- [marks_entry_alevel.php](marks_entry_alevel.php)

---

## 17. Masuala ya Kawaida na Suluhisho

### 17.1 Ukiona page inarudi nyuma au haifunguki
- Hakikisha umeingia na role sahihi.
- Hakikisha `teaching_level` ni sahihi.
- Hakikisha teacher amepewa darasa/stream sahihi.
- Hakikisha unaenda kwenye router sahihi ya marks.

### 17.2 Matokeo hayahifadhi
- Hakikisha umejaza subject, class, stream, year, exam type.
- Hakikisha connection ya database iko sawa.
- Hakikisha form haijafungwa na lock.

### 17.3 SMS haijatumwa
- Hakikisha API endpoint na key vimejazwa.
- Au tumia Phone SMS mode.

### 17.4 Ripoti hazionyeshi data
- Hakikisha marks zimeingizwa kwenye table sahihi kulingana na level.
- Primary, Nursery, O-Level na A-Level zina tables tofauti.

---

## 18. Viambatisho vya Picha

Sehemu hizi ndizo unaweza kuweka screenshots zako mwenyewe:

- [WEKA PICTURE HAPA: Login Page]
- [WEKA PICTURE HAPA: Admin Dashboard]
- [WEKA PICTURE HAPA: Teacher Dashboard]
- [WEKA PICTURE HAPA: Student Dashboard]
- [WEKA PICTURE HAPA: Add Teacher Page]
- [WEKA PICTURE HAPA: Edit Teacher Page]
- [WEKA PICTURE HAPA: Students List Page]
- [WEKA PICTURE HAPA: Register Student Modal/Page]
- [WEKA PICTURE HAPA: Send SMS Page]
- [WEKA PICTURE HAPA: School Settings Page]
- [WEKA PICTURE HAPA: Attendance Page]
- [WEKA PICTURE HAPA: System Logs Page]
- [WEKA PICTURE HAPA: Primary Marks Entry Page]
- [WEKA PICTURE HAPA: Nursery Marks Entry Page]
- [WEKA PICTURE HAPA: O-Level Marks Entry Page]
- [WEKA PICTURE HAPA: A-Level Marks Entry Page]
- [WEKA PICTURE HAPA: Results Dashboard]
- [WEKA PICTURE HAPA: Broadsheet / Report Page]
- [WEKA PICTURE HAPA: Locked Entry / Request Unlock]

---

## 19. Hitimisho

Mwongozo huu umeandaliwa kusaidia watumiaji wote wa mfumo wa Likindy Digital Management System kuelewa matumizi ya msingi na ya juu ya mfumo.

Kwa marekebisho zaidi, picha halisi za screen na taarifa za ziada zinaweza kuongezwa kwenye sehemu za picha zilizowekwa.

**Asante kwa kutumia Likindy Digital Management System.**
