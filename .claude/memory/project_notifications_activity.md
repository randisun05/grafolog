---
name: Notifikasi & log aktivitas
description: Inbox + email + broadcast + activity log (2026-10-18)
type: project
---
Notifier tunggal (inbox_messages + NotificationMail antre; kandidat B2B inbox saja). Broadcast admin per role. LogActivity middleware menyimpan metadata saja, retensi 90 hari (`activity:prune`), config/activity.php. FE: lonceng navbar, /kotak-masuk, /admin/broadcast, /admin/log-aktivitas. Suite 507 hijau.
**Why:** permintaan user "notif email + kotak masuk, logging ringan".
**How to apply:** tambah notifikasi baru lewat Notifier, jangan Mail langsung.
