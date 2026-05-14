# SMS Event Logging — FreePBX Module

A FreePBX admin module that reports on all SMS messages flowing through
**simontelephonics/smsconnector**, including internal extension-to-extension
messages that bypass smsconnector entirely.

---

## Requirements

- FreePBX 16 or 17
- [simontelephonics/smsconnector](https://github.com/simontelephonics/smsconnector) installed
- PHP 7.4+
- Chart.js (bundled with FreePBX)

---

## How It Works

| Message type | How it's stored | What logs it |
|---|---|---|
| Inbound (external → extension via smsconnector broadcast) | Written to `sms_messages` by smsconnector | Automatic — no extra config |
| Outbound (extension → external via smsconnector) | Written to `sms_messages` by smsconnector | Automatic — no extra config |
| Inbound routed to ext (`route_to_ext`) | **Not** written by smsconnector | `smslog_insert.php` via dialplan |
| Internal (ext → ext, ≤3 digits) | **Not** written by smsconnector | `smslog_insert.php` via dialplan |

---

## Installation

### 1. Copy module files

```bash
cp -r smslog/ /var/www/html/admin/modules/smslog/
chown -R asterisk:asterisk /var/www/html/admin/modules/smslog/
```

### 2. Install the module

```bash
fwconsole ma install smslog
fwconsole reload
```

### 3. Deploy the dialplan logger script

```bash
cp smslog_insert.php /var/www/html/smsconn/smslog_insert.php
chown asterisk:asterisk /var/www/html/smsconn/smslog_insert.php
chmod 750 /var/www/html/smsconn/smslog_insert.php
```

### 4. Update your dialplan

Add `smslog_insert.php` calls to the two dialplan paths that bypass
smsconnector. Edit `/etc/asterisk/extensions_custom.conf` (or wherever
your `[sms-in]` / `[sms-out]` contexts live):

#### sms-out, internal extension — add before Hangup()

```ini
exten => internal,1,NoOp(Internal message from ${NUMBER_FROM} to ${NUMBER_TO})
 same => n,Set(CONTACTS=${PJSIP_DIAL_CONTACTS(${NUMBER_TO})})
 same => n,Set(CONTACT_COUNT=${FIELDQTY(CONTACTS,&)})
 same => n,Set(i=1)
 same => n,While($[${i} <= ${CONTACT_COUNT}])
 same => n,Set(CURRENT_CONTACT=${CUT(CONTACTS,&,${i})})
 same => n,Set(SIP_URI=${CUT(CURRENT_CONTACT,/,3)})
 same => n,MessageSend(pjsip:${SIP_URI},${MESSAGE(from)})
 same => n,Set(i=$[${i} + 1])
 same => n,EndWhile()
 same => n,NoOp(Internal send status is ${MESSAGE_SEND_STATUS})
 ; ── SMS Event Log ──────────────────────────────────────────────────────────
 same => n,Set(ENV(QUERY_STRING)=direction=internal&src=${NUMBER_FROM}&dst=${NUMBER_TO}&body=${URIENCODE(${MESSAGE(body)})}&status=${MESSAGE_SEND_STATUS})
 same => n,Set(ENV(REQUEST_METHOD)=GET)
 same => n,System(php /var/www/html/smsconn/smslog_insert.php)
 same => n,Set(ENV(QUERY_STRING)=)
 ; ───────────────────────────────────────────────────────────────────────────
 same => n,Hangup()
```

#### sms-in, route_to_ext — add before Hangup()

```ini
exten => route_to_ext,1,NoOp(Routing reply from ${FROM} to extension ${TARGET_EXT})
 same => n,Set(CONTACTS=${PJSIP_DIAL_CONTACTS(${TARGET_EXT})})
 same => n,Set(CONTACT_COUNT=${FIELDQTY(CONTACTS,&)})
 same => n,Set(i=1)
 same => n,While($[${i} <= ${CONTACT_COUNT}])
 same => n,Set(CURRENT_CONTACT=${CUT(CONTACTS,&,${i})})
 same => n,Set(SIP_URI=${CUT(CURRENT_CONTACT,/,3)})
 same => n,MessageSend(pjsip:${SIP_URI},${MESSAGE(from)})
 same => n,Set(i=$[${i} + 1])
 same => n,EndWhile()
 ; ── SMS Event Log ──────────────────────────────────────────────────────────
 same => n,Set(ENV(QUERY_STRING)=direction=inbound&src=${FROM}&dst=${TARGET_EXT}&body=${URIENCODE(${MESSAGE(body)})}&status=received)
 same => n,Set(ENV(REQUEST_METHOD)=GET)
 same => n,System(php /var/www/html/smsconn/smslog_insert.php)
 same => n,Set(ENV(QUERY_STRING)=)
 ; ───────────────────────────────────────────────────────────────────────────
 same => n,Hangup()
```

Then reload the dialplan:

```bash
fwconsole reload
# or: asterisk -rx "dialplan reload"
```

---

## Navigate to the report

**Reports → SMS Event Log**

---

## Features

- **7 stat cards** — Total, Outbound, Inbound, Internal, Delivered, Undelivered, Unread
- **Volume bar chart** — daily inbound/outbound/internal breakdown, 7/30/90 days
- **Filter bar** — date range, direction (inbound/outbound/internal), delivered,
  read/unread, adaptor (auto-populated), DID (auto-populated), from/to number,
  thread ID, free-text search
- **Thread filter shortcut** — click thread icon on any row to filter that conversation
- **Event detail modal** — all fields, full message body
- **CSV export** — respects current filter set

---

## smslog_insert.php — How It Works

The script is called via Asterisk's `System()` application, reading parameters
from `ENV(QUERY_STRING)` — the same pattern used by `provider.php` in smsconnector.

It inserts directly into `sms_messages` with:

- `direction` set to `internal`, `in`, or `out`
- `adaptor` set to `dialplan` (so you can filter these in the UI)
- `threadid` built as a sorted pair of the two numbers (`101_5551234567`)
  matching smsconnector's convention so conversations thread together correctly
- `delivered` derived from Asterisk's `MESSAGE_SEND_STATUS` variable
- `didid` resolved by looking up the DID number in `smsconnector_dids` if provided

---

## File Layout

```
smslog/
├── module/
│   ├── Smslog.class.php      ← BMO class — all DB queries
│   ├── module.xml            ← FreePBX manifest
│   └── page.smslog.php       ← Page controller + AJAX endpoints
├── views/
│   └── main.php              ← Admin page HTML
├── assets/
│   ├── js/smslog.js          ← Front-end logic
│   └── css/smslog.css        ← Styles
├── smslog_insert.php         ← Deploy to /var/www/html/smsconn/
└── README.md
```

---

## License

GPL v2 — https://www.gnu.org/licenses/gpl.txt
