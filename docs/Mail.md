# totmannschalter – Mail delivery notes
## Mail delivery (prerequisite)
Mail is sent via the configured sendmail binary (`sendmail_path` in `totmann.inc.php`) in sendmail-compatible mode.

Manual pipe test:
```sh
# Adjust `/usr/sbin/sendmail` to match totmann.inc.php sendmail_path
printf "From: test <test@example.com>\nTo: you@example.com\nSubject: pipe-test\n\nhello\n" | /usr/sbin/sendmail -i -- you@example.com
echo $?
```
## SMTPUTF8 pitfalls (some recipients reject)
Some receiving servers reject messages unless your sending MTA offers SMTPUTF8 when headers contain raw non-ASCII. This project RFC2047-encodes headers to avoid SMTPUTF8 in typical cases, but ASCII-only `mail_from` remains the safest option.

Fix options (pick one):
- Use an ASCII-only `mail_from` (recommended for maximum compatibility).
- If your mail setup still emits SMTPUTF8, ensure your outbound MTA supports and advertises SMTPUTF8 (advanced; depends on your mail setup).
## Deliverability (practical)
Even with SPF/DKIM/DMARC, identical short mails sent frequently can look spammy (especially to large providers like Google or Microsoft).

Suggestions:
- During real use: keep reminder frequency human-scale (hours, not minutes).
- Avoid repeating identical subject/body too often (especially during tests).
- Use an ASCII-only display name in `mail_from` (some filters dislike “unusual” symbols even if technically valid).
- Consider adding a stable plain-text footer (what this is and why the recipient gets it) so content looks less “automated”.
- For tests: use the provided test preset in `docs/Timing.md` (fast but avoids “mail flooding”).

Optional:
- Add a `List-Unsubscribe` header (`mailto:`) if you want to be extra deliverability-friendly (not required, but sometimes helps).
