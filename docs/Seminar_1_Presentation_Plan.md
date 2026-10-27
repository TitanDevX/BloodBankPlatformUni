# Seminar 1 presentation plan

Exactly **10 content slides**, excluding the cover and references. This plan includes only the requested material. No separate previous-work, problem/importance, scope, actors, conclusion, or implementation-progress slides.

Cover, unnumbered: project title, student names, supervisor, university, and seminar date.

## 1. هدف المشروع

**Suggested slide text**

يهدف المشروع إلى تطوير منصة تربط بنوك الدم بالمستشفيات، وتنظّم تسجيل المتبرعين ومتابعة التبرع والفحوصات وتجهيز مكونات الدم وإدارة مخزونها، مع مساعدة المستشفيات على الوصول إلى أقرب بنك قادر على توفير كامل الكمية المطلوبة من المكوّن والزمرة المحددين، ودعم تخطيط الاحتياجات بالاعتماد على البيانات التاريخية.

Adapted from the uploaded graduation report’s project goal (printed page 19), adjusted to the current platform requirements. Present it as an objective, not a claim of completed functionality.

## 2. الدراسة المرجعية: ملخص ومقارنة

**Short abstract**

تناولت الدراسة المرجعية أربعة أبحاث وثلاثة أنظمة مرتبطة بإدارة التبرع ومخزون مكونات الدم وطلبات المستشفيات والتنبؤ بالطلب. واستُخدمت نتائج المقارنة لتحديد وظائف النظام المقترح وقاعدة اختيار البنك القادر على تلبية الطلب.

**Compact comparison**

| العمل المدروس | التركيز مقارنة بالمشروع المقترح |
| --- | --- |
| Rajawat et al. (2025) [1] | عمليات البنك والطلبات؛ التنبؤ مؤجل إلى عمل مستقبلي |
| Shah et al. (2022) [2] | تنظيم قاعدة البيانات والمخزون والتوزيع |
| Kwon et al. (2024) [3] | التنبؤ بالطلب اعتماداً على بيانات نقل الدم |
| Blood Net (2025) [4] | ربط المستشفيات بالمتبرعين حسب الزمرة والموقع |
| e-RaktKosh [5] | إدارة عمليات البنك والبحث عن توفر المكونات |
| BloodNet — Australia [6] | طلب منتجات الدم وإدارة مخزون الجهات المستفيدة |
| Red Cross Donor App [7] | مواعيد المتبرعين وسجل التبرع |
| **النظام المقترح** | **ربط رحلة التبرع بالمخزون والطلبات، واختيار أقرب بنك يوفّر كامل الكمية المطابقة، مع توقع الطلب** |

Use one full-width table below the short abstract. This is a condensation of `docs/Literature_Review.docx`, not an independent verification of its sources. Keep exact references outside the ten content slides. Do not describe the proposed system as proven superior or infer that an undocumented feature is absent.

## 3. تحليل المتطلبات — Requirements Analysis

**Suggested slide content**

تم تنظيم المتطلبات الوظيفية في 24 متطلباً تشمل:

- تسجيل الجهات وإدارة حسابات الموظفين: FR-01–FR-07.
- ملفات المتبرعين: FR-08–FR-10.
- محاولة التبرع والأهلية وجمع الدم: FR-11–FR-15.
- تجهيز المكونات والفحص المخبري: FR-16–FR-17.
- طلبات المستشفيات والمطابقة والقبول: FR-18–FR-20.
- توقع الطلب: FR-21–FR-22.
- انتهاء الصلاحية وتوثيق الإتلاف: FR-23–FR-24.

Source: `docs/BloodBank_Functional_Requirements.docx`. Show the groups rather than the full 24-row specification.

## 4. مخطط حالات الاستخدام — Use Case Diagram

Show the system use-case diagram with its actual actors. Explain the principal interactions briefly and highlight FR-19 for the next slide.

Existing candidate asset: `docs/Diagrams/Use Case/Usecase.png`. Check it against the current requirements before insertion. Keep actor explanations on this slide rather than adding an actors slide.

## 5. توصيف حالة استخدام واحدة — Use Case Specification

Recommended selection: **FR-19 — View Request Match / عرض البنك المطابق**.

| الحقل | المحتوى المختصر |
| --- | --- |
| المستخدم الرئيسي | موظف المستشفى |
| الهدف | عرض أقرب بنك يوفّر كامل كمية المكوّن والزمرة ABO/Rh المحددين |
| الشروط السابقة | مستخدم مخوّل وبيانات الطلب والموقع اللازمة متاحة |
| المسار الأساسي | إدخال البيانات ← طلب البحث ← فحص المخزون الصالح والمتاح ← تحديد البنوك التي تغطي كامل الكمية ← عرض الأقرب |
| المسار البديل | عدم وجود بنك مؤهل: عرض «لا توجد مطابقة» وخيار إعادة المحاولة |
| الاستثناء | تعذر قراءة المخزون أو الموقع: إظهار تعذر إتمام البحث |
| الشروط اللاحقة | عرض النتيجة دون حفظ طلب أو حجز مكونات |

Source: `docs/use-cases-specificaitons/use-case-specifications-en.docx`. FR-19 is a suggested choice for this plan. Keep the same choice across slides 5–7.

## 6. مخطط التسلسل — Sequence Diagram

Show **one sequence diagram for FR-19**.

Suggested lifelines: Hospital Staff → React Interface → Laravel Matching Service → Inventory and Bank Data.

Messages: enter search data; submit query; validate permissions and input; read candidate banks and eligible stock; select the nearest bank with the complete exact-match quantity; return the result.

Use alternative branches for a match, no match, and failure to retrieve required data. Searching does not save a request or reserve units.

## 7. مخطط النشاط — Activity Diagram

Show **one activity diagram for FR-19**.

Flow: enter request data → validate → read bank locations and eligible stock → filter banks by exact component, ABO/Rh, and full quantity → select nearest qualifying bank → display result.

Include branches for invalid data, no match, and a data retrieval failure. Retry returns to a fresh search. Submission belongs to FR-18 and need not be expanded here.

## 8. ERD ومخطط الأصناف — Class Diagram

Show **both required diagrams on this one slide** to meet the ten-slide limit.

- Left: a readable ERD with core entities, keys, relationships, and cardinalities.
- Right: a matching class diagram with core classes, associations, and a few relevant operations.

Keep each diagram to the same coherent core: Donor, DonationAttempt, BloodCollection, BloodComponent, BloodBank, Hospital, HospitalRequest, and RequestAllocation. These are suggested analysis labels; align them with the selected project model before assembling the diagrams. Omit secondary details from this overview rather than shrinking a complete schema to unreadable text.

Explain the distinction briefly: the ERD describes stored data and relationships; the class diagram describes objects, responsibilities, and behavior.

Candidate ERD source: `docs/ER/blood-bank-erd-overview.mmd`. The repository has multiple ERD versions; use one agreed version consistently. The class diagram still needs to be prepared or supplied. This plan does not claim either diagram is finalized.

## 9. الأدوات والتقنيات المستخدمة

| التقنية | الاستخدام |
| --- | --- |
| React | بناء واجهات المستخدم والنماذج وعرض البيانات |
| Laravel | بناء API وتطبيق قواعد العمل والتحقق من الصلاحيات |
| MySQL | تخزين البيانات والعلاقات بين سجلات النظام |

Simple architecture line: React → Laravel API → MySQL.

These three technologies are sufficient for this slide. Do not add tools or frameworks the team has not selected.

## 10. خطة تنفيذ المشروع — Gantt Chart

Show one readable Gantt chart using the team’s selected schedule.

Existing candidates:

- `outputs/gantt-early-dec-2026/Blood_Bank_Graduation_1_Gantt_Early_December.xlsx`
- `outputs/gantt-2026-10-25/Blood_Bank_Graduation_1_Waterfall_Gantt.xlsx`

Use a complete chart with legible task labels and dates. The existing generator treats 8 December 2026 as an assumed deadline, so use the approved schedule when assembling the slide. Do not add a separate execution-stages slide.

## Outside the ten content slides

- Cover.
- References: reproduce the seven references from `docs/Literature_Review.docx` and acknowledge the uploaded graduation report for the adapted goal text.

No additional content sections are planned.
