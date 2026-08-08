# SkyDesk — Product Brief for Marketing Copy (External AI)

> Use this document as the **only source of truth** for marketing texts (landing, ads, LinkedIn, etc.).
> Do **not** invent features that are not listed here.
> Do **not** mention personal names, private emails, internal repo names (`natassg`), seed users, PINs, or credentials.
> Present SkyDesk as a **public SaaS product** (even if the first release is invite-only).

---

## 1. Product one-liner

**RU:** SkyDesk — рабочее пространство личного помощника: поручения, календарь, деньги на руках и отчёты руководителю — всё в одном окне вместо блокнота и чатов.

**EN:** SkyDesk is a personal assistant workspace: tasks, calendar, cash on hand, and reports for the executive — all in one window instead of a notepad and chat threads.

---

## 2. Positioning

| | |
|---|---|
| **Category** | Personal assistant OS / executive assistant workspace (not CRM, not ERP, not “business management”) |
| **Primary user (hero)** | Personal / executive assistant (the person who captures and executes day-to-day work) |
| **Secondary user** | Executive / principal (reads status via shared report links and public cashflow view) |
| **Form factor** | Mobile-first **PWA** (install on home screen; phone is the main device) |
| **Core promise** | Faster capture + one place for work, calendar, and money — better than a notepad |
| **Anti-positioning** | Not a CRM. Not project management for teams of 50. Not accounting software. Not a messenger. |

**Key pain (approved):** work is scattered across chats, notes, and memory. Assistant needs **everything in one window** — clearer and faster than a notepad.

**Job to be done:** capture a request in seconds, track it to done, attach calendar and money when needed, and show the executive a clean status without a call.

---

## 3. Audience

### Primary — Personal / Executive Assistant
- Receives tasks by phone, messenger, or in person
- Needs speed of capture over heavy process
- Juggles meetings, trips, errands, advances, receipts
- Reports back to one principal (or a small circle)

### Secondary — Executive / Principal
- Wants visibility without micromanagement
- Opens a shared report link or cash overview when needed
- Does not live inside the app every day

### Not for (yet)
- Large multi-team project portfolios
- Full company finance / bookkeeping
- Customer pipeline / sales CRM

---

## 4. Value pillars (landing structure hint)

Suggested landing: **Hero + 3–5 feature blocks + CTA**. Hero = assistant’s world. Language: **RU + EN**.

### Pillar A — Capture in seconds
- New task in a few taps
- Hierarchy (subtasks), status, priority, type, deadline, attachments (photos compressed on device, documents)
- Comments and reminders

### Pillar B — Calendar next to work
- Meetings, trips, personal events
- Tasks and events can be linked (many-to-many)
- Deadlines visible in the calendar context

### Pillar C — Money “on hand”
- Wallet + advances − unassigned expenses = **cash on hand**
- Advance lifecycle: request → received → on report → closed
- Expenses with optional vendor, category, receipt photo
- Clear movement ledger (income / expense / transfer)

### Pillar D — Report without a meeting
- Compose a period report (closed/active tasks, events, finance snapshot)
- Share a **public link** for the executive
- Optional public cashflow view (PIN-protected)

### Pillar E — Built for the phone
- PWA: home-screen install, mobile UI as default
- Offline **reading** supported; mutations when online
- Telegram: login (if account linked) + reminders / morning & evening digests

*(Landing can use 3–5 of these; do not force all five if copy feels crowded.)*

---

## 5. Feature inventory (factual)

Use for accuracy; marketing may group/simplify.

**Auth & roles**
- Email/password; Telegram WebApp login when linked
- Roles: operator (assistant) vs admin (sees everything)
- Settings: profile, password, theme, users (admin), editable dictionaries (statuses, priorities, task/event types, expense categories, disbursement methods)

**Home**
- Attention queue: active tasks, waiting for funding, today/tomorrow events, advances on report

**Tasks**
- Unlimited nesting; cascade close; “promote to root”
- N↔N with calendar events; multiple advance requests; attachments; reminders

**Calendar**
- Full calendar UI; event detail; link to tasks

**Finance**
- Wallet, advances, expenses, vendors, categories
- Overspend on advance takes remainder from wallet
- After advance close: remainder → wallet or write-off without report

**Contacts**
- People and organizations; optional link to vendors

**Reports**
- Period compose; public token link; accept flow for executive

**Extensibility (approved message)**
- Platform is modular; **additional modules can be developed on request / for a client**
- Do not invent a marketplace or plugin store unless later confirmed

---

## 6. Tone & brand voice

- Calm, practical, professional — “tool for serious daily work”
- Speak **to the assistant** first (“you capture, track, report”), executive second
- Avoid startup hype, purple-AI clichés, “synergy”, fake metrics
- Avoid implying replacement of accountants, lawyers, or full office suites
- Product name: **SkyDesk** (keep as-is in RU and EN)
- UI language of the product today: Russian; marketing site: RU + EN

**Words that fit:** clear, fast capture, one window, on hand, report link, mobile, PWA, assistant  
**Words to avoid:** CRM, ERP, AI-powered (unless a real AI feature ships), enterprise suite, disrupt

---

## 7. Landing brief (for later implementation)

| Block | Intent |
|---|---|
| **Hero** | Brand **SkyDesk** dominant; one headline; one short line; CTA **Sign in / Войти** (+ optional second CTA **Request access / Запросить доступ**) |
| **3–5 features** | From pillars A–E; one job per section; short copy |
| **Extensibility** | Short note: custom modules on request |
| **CTA close** | Repeat Sign in / Request access |
| **Visuals** | Start simple; product screenshots will be added later (phone frames of Tasks / Finance / Report) |
| **Route** | Replace `/` (login via button, not auto-redirect forever) |

Do **not** invent pricing, SLAs, SOC2, or “used by N companies” unless provided later.

---

## 8. Sample angles (draft seeds — external AI may rewrite)

**RU headlines (seeds):**
- Вся работа помощника — в одном окне
- Быстрее блокнота. Спокойнее чата.
- Поручение → календарь → деньги → отчёт руководителю

**EN headlines (seeds):**
- An assistant’s whole day — in one window
- Faster than a notepad. Calmer than chat.
- Capture. Schedule. Track cash. Report.

**RU supporting:**
- Фиксируйте поручения за секунды, держите события и авансы рядом, отправляйте руководителю ссылку на отчёт — без созвона «ну что там».

**EN supporting:**
- Capture requests in seconds, keep events and advances beside the work, and send the executive a report link — no “quick status call” required.

---

## 9. CTA & go-to-market stage

- Current stage: product works; used privately; preparing a **public SaaS try**
- Primary CTA: **Войти / Sign in**
- Secondary CTA (optional): **Запросить доступ / Request access** (invite-only is OK to say if true at launch)
- Do not claim open self-serve signup with free forever plan unless confirmed

---

## 10. What NOT to say

- Personal story names, family roles as marketing facts, private domains
- Internal tech dump on the landing (Laravel, Vue, MySQL) — OK in a developer README, not in hero marketing
- “AI”, blockchain, marketplace, team chat, HR, CRM pipeline
- Guaranteed offline editing, multi-currency accounting, bank sync (not in product)
- Fake social proof

---

## 11. Tech note (optional footer / “Built with”, not hero)

Stack (for accuracy if asked): Laravel 11, Inertia, Vue 3, Vuetify 4, MySQL, PWA.  
Hosted as a web app / installable PWA.

---

## 12. Deliverables expected from external AI

Please produce (RU + EN, paired):

1. Landing hero: eyebrow (optional), headline, subline, primary + optional secondary CTA labels  
2. 4 feature sections: title + 1–2 sentence body each (choose from pillars; include money + report at least once)  
3. Short “Custom modules” blurb  
4. Closing CTA block  
5. Meta title + meta description (≤155 chars)  
6. 3 Twitter/X-length variants and 1 LinkedIn-length post (optional)

Keep claims aligned with this brief. Prefer concrete verbs over adjectives.
