# Test System Maintenance Platform — Use Cases (V1)

**Stack:** Linux, Nginx, PostgreSQL, PHP 8.2+/Laravel (LEPP)
**Scale:** ~300 test systems, ~6,000–9,000 individually tracked devices, multi-site
**Status:** Draft for review — no code written yet

---

## 1. Core Entities (for reference)

These aren't use cases themselves, but every use case below refers to them, so it's worth fixing the vocabulary first.

| Entity | Description |
|---|---|
| **Site** | A physical location. Filter/reporting dimension, not an access boundary. |
| **Device** | An individually tracked piece of electronics (measurement, load emulation, emulation, automotive comms, or signal generation). Has an internal **asset tag** (primary ID) and manufacturer **serial number** (secondary). Belongs to exactly one Site at a time, and to at most one Test System at a time (or unassigned/in storage). Never deleted — only retired. |
| **Test System** | A collection of Devices, some fixed (chassis/wiring) and some swappable. Free-form composition — no fixed slots. Belongs to a Site. |
| **Assignment** | A historical record of a Device being attached to a Test System over a time period. |
| **Transfer** | A historical record of a Device moving between Sites. |
| **Booking** | A reservation of a Test System for a time window, snapshotting which Devices were assigned at booking time. |
| **Maintenance Event** | A record of Calibration, Preventive Maintenance, or Repair performed on a Device or System. Calibration/PM are recurring (calendar-based); Repair is reactive. |
| **Fault Report** | A user-submitted flag that a Device/System needs repair. Open → In Progress → Resolved. |
| **Conflict** | A detected overlap between a Booking and a Maintenance window, open Fault, or Site transfer. Always "warn but allow, log the override." |
| **User** | A person with one of 5 roles: Engineer, Technician, Scheduler/Manager, Admin, Auditor. Has a selected display language (locale) for the GUI; affects presentation only, never stored data. |
| **API Key** | A scoped service-account credential for system-to-system integration, independent of any human user. |

---

## 2. Roles & Permissions Summary

| Capability | Engineer | Technician | Scheduler/Mgr | Admin | Auditor |
|---|:---:|:---:|:---:|:---:|:---:|
| View devices/systems/history/reports | ✅ | ✅ | ✅ | ✅ | ✅ (read-only) |
| Book a test system | ✅ | ✅ | ✅ | ✅ | ❌ |
| Report a fault | ✅ | ✅ | ✅ | ✅ | ❌ |
| Log calibration/maintenance/repair | ❌ | ✅ | ✅ | ✅ | ❌ |
| Create/edit devices, systems, assignments | ❌ | ✅ | ✅ | ✅ | ❌ |
| Transfer device between sites | ❌ | ✅ | ✅ | ✅ | ❌ |
| Override booking/transfer/fault conflicts | ❌ | ❌ | ✅ | ✅ | ❌ |
| Bulk import devices | ❌ | ✅ | ✅ | ✅ | ❌ |
| Manage users & roles | ❌ | ❌ | ❌ | ✅ | ❌ |
| Manage API keys | ❌ | ❌ | ❌ | ✅ | ❌ |
| Export reports (predefined + custom) | ✅ | ✅ | ✅ | ✅ | ✅ |
| View full audit log | ❌ | ❌ | ✅ (own scope) | ✅ | ✅ |

---

## 3. Use Cases

### 3.1 Device & System Management

**UC-1: Create a Device**
A Technician/Scheduler/Admin creates a new device record: category (1 of 5 fixed types), manufacturer, model, serial number, site, status. System auto-generates a unique internal asset tag and an associated barcode/QR code.

**UC-2: Bulk Import Devices**
A Technician/Scheduler/Admin uploads a CSV/Excel file of devices. System validates each row (duplicate asset tags, missing required fields, invalid category/site references), creates valid rows, and returns a results report listing successes and per-row failures with reasons, so bad rows can be corrected and re-imported.

**UC-3: Edit a Device Record**
Update attributes of an existing device (manufacturer info, status, notes). Change is captured in audit history.

**UC-4: Retire a Device**
Mark a device inactive/retired. Record is preserved permanently; device disappears from active-assignment/booking pools but remains visible in history and reports.

**UC-5: Create a Test System**
Define a new test system: name, site, status. Systems start with no assigned devices.

**UC-6: Assign a Device to a Test System**
Attach a device (status: unassigned/in storage) to a test system. Closes any prior open assignment for that device, opens a new one. If the device has an open fault or maintenance conflict with current bookings, system flags it (warn, don't block).

**UC-7: Unassign a Device from a Test System**
Remove a device from its current system; device becomes unassigned/in storage. Closes the open assignment record with an end timestamp.

**UC-8: View Device Detail**
See a single device's full picture: current site, current system assignment, full assignment history, full transfer history, calibration/maintenance history, open/past fault reports, current status.

**UC-9: View Test System Detail**
See a system's current device roster, booking calendar, maintenance history, and open faults.

**UC-10: Transfer a Device Between Sites**
Move a device's site. If currently assigned to a system, flag the conflict (warn, allow override, log it). Creates a transfer history record.

**UC-11: Generate/Print Asset Labels**
Generate a barcode/QR label for one device or a batch of devices, selecting a label template (thermal printer format or Avery sheet-grid format), and produce a printable PDF.

**UC-12: Look Up a Device by Scanning**
From a mobile browser, scan a device's barcode/QR code using the camera to jump directly to its detail page (UC-8).

---

### 3.2 Calibration & Maintenance

**UC-13: Define a Calibration/Maintenance Schedule for a Device**
Set a recurring interval (calendar-based, e.g. every 12 months) for calibration or preventive maintenance on a device. System calculates the next due date automatically going forward.

**UC-14: Log a Calibration Event**
Technician records a completed calibration: date performed, who/where (in-house or named external vendor), pass/fail result, next due date (auto-suggested from the interval, editable). (Certificate file attachment deferred to a later release — field reserved in the data model.)

**UC-15: Log a Preventive Maintenance Event**
Technician records completed PM work (cleaning, firmware update, inspection, etc.): date, performed by, notes, next due date if recurring.

**UC-16: Log a Repair**
Technician records a completed repair, optionally linked to a closing Fault Report (UC-18): date, performed by, description of work, parts/cost notes (optional), resulting device status (returned to service / retired / sent out).

**UC-17: Mark a Device "Out for External Calibration"**
When a device is sent to an outside vendor, mark it unavailable (physically absent) — excludes it from new system assignments and flags booking conflicts for any system it's currently in.

**UC-18: Report a Fault**
Any authenticated user flags a device or system as faulty: description, severity (optional), timestamp, reporter. Creates an open Fault Report. Flags conflicts on any current/future bookings for that device/system.

**UC-19: Triage & Resolve a Fault Report**
Technician/Scheduler updates a fault report's status (Open → In Progress → Resolved), optionally linking it to the eventual Repair record (UC-16) that closes it.

**UC-20: View Upcoming/Overdue Calibration & Maintenance**
Dashboard/list view of all devices with calibration or PM due within a configurable window (e.g. next 30 days), and all overdue items, filterable by site/category.

---

### 3.3 Usage Planning (Booking)

**UC-21: Book a Test System**
A user reserves a test system for a date/time range. System snapshots the currently-assigned devices into the booking record. System checks for conflicts (UC-22) and surfaces warnings but allows the booking to proceed.

**UC-22: Detect Booking Conflicts**
When creating/editing a booking, check the target system and its currently-assigned devices against: (a) scheduled maintenance/calibration windows, (b) open fault reports, (c) pending site-transfer conflicts. Surface all detected conflicts; do not block.

**UC-23: Override a Booking Conflict**
A Scheduler/Manager/Admin proceeds with a flagged booking despite a conflict. The override (who, why, what was overridden) is recorded in the audit log.

**UC-24: View System Availability Calendar**
A visual calendar/Gantt-style view showing booking and maintenance windows across test systems, filterable by site, category, and status, scoped to a manageable subset (not all 300 systems at once).

**UC-25: Edit or Cancel a Booking**
Modify a booking's time range or cancel it outright. Re-runs conflict detection (UC-22) on edit.

---

### 3.4 Reporting

**UC-26: Run a Predefined Report**
Select from a list of standard reports (e.g. utilization by system/site, overdue calibration, upcoming maintenance, fault report summary, audit log export, booking conflict/override log) and generate it on demand.

**UC-27: Export a Report**
Export any predefined or custom report as PDF or Excel/CSV.

**UC-28: Build a Custom Report**
Power users select entities, fields, and filters to construct an ad-hoc report against the underlying data (devices, systems, bookings, maintenance events, faults), reusing the same query layer as predefined reports.

---

### 3.5 Notifications

**UC-29: Receive Due-Soon/Overdue Alerts**
Automated daily/scheduled check identifies devices approaching or past their calibration/maintenance due date and sends notifications via email and in-app notification center to the relevant role-based distribution list.

**UC-30: Receive Conflict/Override Alerts**
When a booking conflict is overridden (UC-23), or a fault is reported (UC-18) on a device with upcoming bookings, notify the relevant role-based distribution list.

**UC-31: View In-App Notification Center**
A user views their relevant pending/recent notifications within the GUI.

---

### 3.6 Administration & Access Control

**UC-32: Manage Users & Roles**
Admin creates/edits/deactivates user accounts and assigns one of the 5 roles.

**UC-33: Manage API Keys**
Admin creates a scoped API key/service account (defining what it can read/write), views existing keys, revokes a key.

**UC-34: View Audit Log**
Auditor/Admin/Scheduler views a chronological, filterable record of who changed what and when across the system (device edits, assignments, transfers, calibration logs, booking overrides, fault reports, user/role changes).

---

### 3.7 API (System-to-System)

**UC-35: Authenticate via API Key**
An external system authenticates a request using a scoped API key in lieu of a human login.

**UC-36: Query Devices/Systems/Bookings via API**
An external system reads device, system, calibration, or booking data programmatically, subject to the calling key's scope.

**UC-37: Submit Usage Data via API**
A test execution system reports usage information (e.g. operating hours, test cycles, completion of a booking) back to the platform programmatically. (Usage-hours field reserved in V1 schema even though scheduling logic is calendar-only for now, per our discussion.)

**UC-38: Create/Update Records via API**
A scoped integration creates or updates records (e.g. logging a fault, creating a booking) within its key's permitted scope.

---

### 3.8 Internationalization

**UC-39: Display GUI in User's Selected Language**
A user selects their preferred display language from their profile/account settings. All UI strings (labels, buttons, menus, validation/error messages, email notification templates) render in that language. Underlying code, database schema, and stored data remain in English regardless of display language — only presentation is translated.

**UC-40: Add a New Language**
Admin/developer adds support for a new display language by supplying a translated string file. No schema or code changes required — purely a translation/content task.

---

### 3.9 Documentation Deliverables

**UC-41: User Task Guides**
Short, self-contained how-to guides written per feature/task (e.g. "How to Book a Test System," "How to Log a Calibration Event," "How to Override a Booking Conflict"), shipped alongside each feature as it's completed rather than as one large end-of-project effort. Each guide maps closely to a use case in Section 3.

**UC-42: Role-Based Onboarding Guides**
A landing/orientation page per role (Engineer, Technician, Scheduler/Manager, Admin, Auditor) summarizing what that role can do and linking out to the relevant Task Guides (UC-41). Navigation scaffolding, not duplicated content.

**UC-43: System Architecture Documentation (One-Time, Post-Stabilization)**
Once V1 is stable, produce: a topology diagram (Nginx / PHP-FPM / PostgreSQL / Docker layout, request flow), and activity diagrams for key workflows (e.g. booking with conflict detection, calibration logging, fault report lifecycle). Treated as a stable snapshot, not a continuously-maintained living document — re-done at major version milestones, not on every change.

---

## 4. Explicitly Deferred (Not V1, But Designed For)

These were discussed and intentionally pushed past V1 — noted here so they aren't forgotten, and so V1's data model doesn't have to be reworked to add them later:

- **Usage-hours-based calibration/maintenance scheduling** (calendar-only for V1; usage data capture starts now via UC-37, scheduling logic on top comes later)
- **Calibration certificate file attachments** (data model reserves the relationship; upload/storage UI comes later)
- **Personal (per-user) API tokens** (V1 ships scoped service-account keys only)
- **SSO/LDAP authentication** (V1 ships standalone username/password)
- **Additional display languages beyond English** (German, Polish, and possibly others later — UI string translation framework is built into V1 from day one per UC-39/40, but only an English string file ships; adding a language is a translation task, not a re-engineering effort)
- **Right-to-left (RTL) layout support** (only relevant if a future language requires it, e.g. Arabic/Hebrew — not needed for German/Polish/Russian; flag explicitly if it becomes relevant)

## 5. Open Infrastructure Item (Non-Blocking)

Target platform is **PHP 8.2+/Laravel**, which cannot run on the current production PHP 7.2.34/SLES 12 SP5 environment. Likely path: Docker containers (PHP-FPM, Nginx, PostgreSQL) running on the existing SLES 12 SP5 host, pending infrastructure approval. This does not block development — it's a deployment-target decision to finalize before go-live.
