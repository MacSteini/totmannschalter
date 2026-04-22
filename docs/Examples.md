# totmann – Example messages
![totmann](../img/totmannschalter-icon.png)

This page shows representative messages that totmann can generate during normal use and during operator-facing problems.

Use it when you want to answer practical questions such as:
- What does a normal self-reminder look like?
- What does an operator warning mail look like?
- What does an escalation mail with ACK and downloads look like?

Important:
- the examples below are representative, not guaranteed byte-for-byte copies of every real mail
- links, tokens, fingerprints, and timestamps are example values
- if you need the underlying configuration model, use [Mail delivery notes](Mail.md "Mail delivery notes")
- if you need to analyse live runtime evidence, use [Log guide](Logs.md "Log guide")

## Self reminder
You will see this when the normal reminder cycle runs and totmann asks you to confirm that you are safe and able to respond.

This message goes to the addresses listed in `to_self`.

It helps because it gives you one clear action, shows the current deadline, and reminds you that escalation may already have started if you react too late.

What to do next:
- open the confirmation link
- complete the button click on the web page
- if the deadline has already passed, check whether escalation mail was already sent

Example:
```text
Subject: Please confirm you are safe

Hello,

This is a reminder to confirm that you are safe and able to respond.

Please use this link to confirm:
https://example.com/totmann.php?a=confirm&id=962bacf5998ec04d7cf8bd6f1303471d&sig=cd375c690d25d8cec913d54eb40df29912760373c1ccde008f1562ab724db59e

Confirmation deadline: Wednesday, 22 April 2026, 12:13:10 Europe/London
Current cycle started: Wednesday, 22 April 2026, 12:04:10 Europe/London

If you confirm after the deadline, escalation may already have started.
```

What to notice:
- the message is direct and action-focused
- it includes the real confirmation link, not only a warning
- the deadline and current cycle timing help you see how urgent the action is

## Operator warning
You will see this when totmann detects an operator-facing configuration or runtime problem but can still continue in best-effort mode.

This message goes to the addresses in `to_self`, not to escalation recipients.

It helps because it tells you what failed, which values to inspect next, and which command to run before trusting the system again.

What to do next:
- read the `Original problem` line first
- run `php totmann-tick.php check` in your state directory
- compare the mentioned values in `totmann-recipients.php` and `totmann.inc.php`
- use [Log guide](Logs.md "Log guide") and [Troubleshooting](Troubleshooting.md "Troubleshooting") if the problem is still unclear

Example: unknown single-use file alias
```text
Subject: Operator warning: Recipient skipped

totmann detected an operator-facing problem and continued in best-effort mode where possible.

Alert type: Recipient skipped
Fingerprint: ba4b8927595f7f3b03b103c8
First seen: Wednesday, 22 April 2026, 11:50:27 Europe/London
Last seen: Wednesday, 22 April 2026, 11:50:27 Europe/London
Occurrences: 1

Original problem:
recipients_file references unknown single-use file alias 'photos' for <totmann@example.com>

What to check next:
Open totmann-recipients.php, fix the referenced row or top-level structure, and rerun php totmann-tick.php check.

Recommended next steps:
1. Change into your state directory: /var/lib/totmann
2. Run: php totmann-tick.php check
3. Inspect totmann.log for matching lines.
4. Compare the affected values in totmann.inc.php and totmann-recipients.php.
5. If you still have the project docs at hand, read docs/Logs.md and docs/Troubleshooting.md.
```

Example: unknown normal file alias
```text
Subject: Operator warning: Recipient skipped

totmann detected an operator-facing problem and continued in best-effort mode where possible.

Alert type: Recipient skipped
Fingerprint: 8d70ce73514a6dc97e26353e
First seen: Wednesday, 22 April 2026, 11:50:27 Europe/London
Last seen: Wednesday, 22 April 2026, 11:50:27 Europe/London
Occurrences: 1

Original problem:
recipients_file references unknown file alias 'letter' for <totmann@example.com>

What to check next:
Open totmann-recipients.php and compare the affected alias with $files plus the field-4/field-5 lists in the affected recipient row.

Recommended next steps:
1. Change into your state directory: /var/lib/totmann
2. Run: php totmann-tick.php check
3. Inspect totmann.log for matching lines.
4. Compare the affected values in totmann.inc.php and totmann-recipients.php.
5. If you still have the project docs at hand, read docs/Logs.md and docs/Troubleshooting.md.
```

What to notice:
- the overall structure stays the same across similar operator warnings
- the fingerprint helps you recognise repeated occurrences of the same underlying problem
- the important variation is usually the `Original problem` line, not the rest of the mail

## Escalation mail to a recipient
You will see this when the configured confirmation window and grace period have passed and totmann starts escalation.

This message goes to one configured escalation recipient, not to `to_self`.

It helps because it can combine 3 things in one place:
- the actual escalation text
- an ACK link so one recipient can confirm receipt
- optional download links, including a visible warning for single-use downloads

What to do next:
- read the message body first
- if you are a recipient and the sender expects receipt confirmation, use the ACK link
- if download links are included, save the required files before forwarding or closing the mail

Example:
```text
Subject: [totmann] EXAMPLE TEMPLATE – message with documents

Hello John Doe,

This is an example escalation message for document delivery.
Please replace it with your own wording before production use.

The files below are included as part of this message.

If you have received this message, please confirm receipt using this link:
https://example.com/totmann.php?a=ack&id=0268c2ac58bb5f4c2ba981e12631dffc&sig=a5bb2896a8f8b29c866057118d407e02432b02b186fb844f10dda948e065bb28

2 Downloads:

https://example.com/totmann.php?a=download&rid=r_a5b1671cc90a333ce4ed14ae1d130529&lid=d_063a550af6022b0a2cab503157df9ff6&evt=1776856832&exp=1792408832&n=94cd0af3e24469fc5802e5ed47bd25f3&sig=8a2ae2d473a9c8f46fc80ccad484a42fad5b67a144da9fcae2793d7e9e1d4eaa

Please save this file straight away. This download link works only once.
https://example.com/totmann.php?a=download&rid=r_a5b1671cc90a333ce4ed14ae1d130529&lid=d_e42562fa5cd81141818febe4a52a0f73&evt=1776856832&exp=1792408832&n=cb98eae425739b8d1e49a5f101a834fd&sig=0d32a1b02963541cbe922457ec39b8ee33f4772c1dde85ced3c644e4056a1e4f
```

What to notice:
- the ACK block appears only when the message uses `{ACK_BLOCK}`
- `2 Downloads:` appears automatically when more than one download link is present
- only the single-use link carries the extra warning text
- the blank line between download blocks makes separate files easier to recognise
