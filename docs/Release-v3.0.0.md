# totmannschalter – Release notes for v3.0.0
## Summary
`v3.0.0` is a breaking-change release for Totmannschalter.

This release focuses on 3 things:
- a much simpler operator model for recipients, messages, and download files
- a more human and language-aware public web interface
- a more fail-safe escalation runtime, especially around partial delivery and acknowledgement handling
## Highlights
- One flat `totmann-recipients.php` now defines reusable files, reusable messages, and the final recipient rows in one place.
- Public web pages now follow the browser language via `Accept-Language` with starter locales `de-DE`, `en-GB`, `en-US`, `fr-FR`, `it-IT`, and `es-ES`.
- Escalation delivery is now persisted per recipient, so failed recipients can be retried without duplicating already successful deliveries.
- `{ACK_BLOCK}` is now the preferred placeholder for the full acknowledgement text plus URL in escalation mails.
- Download handling is clearer for operators: field 4 means normal download links, field 5 means single-use download links.
- Documentation and shipped templates were rewritten for end users, not only for technically experienced operators.
## Breaking changes
- `totmann-recipients.php` now uses 3 flat top-level sections: `$files`, `$messages`, and `$recipients`.
- The earlier PHP-DSL helper model is gone.
- Escalation mail text no longer falls back to `subject_escalate` or `body_escalate` in `totmann.inc.php`.
- Every recipient row must now reference a valid message key in field 3.
- Download configuration is no longer expressed through visible per-download IDs in the operator file.
- Per-file `expires_after_seconds` is gone from the operator file.
- `download_valid_days` in `totmann.inc.php` is now the one global download lifetime setting.
- `single_use` is no longer something operators type as a flag in the recipient file:
	- field 4 means normal downloads
	- field 5 means single-use downloads
- ACK now stops all further escalation mails for the current escalation event once any recipient confirms receipt.
## Upgrade notes
If you are upgrading from an older setup, check these points carefully:
1. Replace the old recipient/message/download setup with the new flat `totmann-recipients.php` structure.
2. Move all escalation mail subjects and bodies into `$messages` inside `totmann-recipients.php`.
3. Ensure every recipient row uses a valid message key in field 3.
4. Move normal download aliases into field 4.
5. Move single-use download aliases into field 5.
6. Replace any old per-file expiry thinking with the global `download_valid_days` setting in `totmann.inc.php`.
7. Review every message body and keep `{ACK_BLOCK}` wherever recipients should be able to confirm receipt.
8. Review every message body and keep `{DOWNLOAD_NOTICE}` wherever field-5 downloads may appear.
9. Replace the placeholder text in `download_notice_single_use` with your own real warning text.
10. Copy the `l18n/` directory into your configured state directory if you want the localised web interface.
## What to verify after updating
- `totmann-recipients.php` still resolves every file alias and every message key correctly.
- Normal downloads appear through field 4 only.
- Single-use downloads appear through field 5 only.
- ACK links appear only in the messages where `{ACK_BLOCK}` is still present.
- Website language follows the browser while timestamps still follow `mail_timezone`.
- An ACK click stops all further escalation mails for that escalation event.
- Existing validation commands still pass:
	- `php -l` on changed PHP files
	- `phpstan` with the project config
	- `quality_tools/bin/validate`
## Suggested release text
Totmannschalter `v3.0.0` is a breaking-change release that simplifies recipient configuration, localises the public web interface, and hardens escalation delivery.

The biggest operator-facing change is the new flat `totmann-recipients.php` model with central `$files`, `$messages`, and `$recipients`. Escalation mail bodies now live only in that file, normal downloads use recipient field 4, single-use downloads use field 5, and the global `download_valid_days` setting replaces per-file expiry complexity.

On the runtime side, escalation delivery is now tracked per recipient, already issued valid download links remain usable even if unrelated config rows later break, and ACK now stops all further escalation mails for the current escalation event.

This release also introduces browser-language website localisation via `Accept-Language` and rewrites the shipped operator documentation and templates for end users.

## Known limitation
- The remaining display-name “ghost comma” issue in some outgoing mail headers is not part of `v3.0.0` and is scheduled for the next release cycle.
