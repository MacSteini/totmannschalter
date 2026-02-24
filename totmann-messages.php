<?php

/**
 * totmannschalter – individual mails
 *
 * Project: https://github.com/MacSteini/totmannschalter
 * Licence: MIT (see LICENCE)
 *
 * How IDs are used:
 * - In `totmann.inc.php`, each `to_recipients` entry can be `[address, id]`.
 * - The optional second value (`id`) selects the matching subject/body pair from this file.
 *
 * Format of this file:
 * - return array where key = `id` and value = ['subject' => string, 'body' => string]
 * - allowed ID regex: ^[a-z0-9_-]+$ (1..100 chars)
 * - if an ID is missing here, runtime falls back to `subject_escalate` + `body_escalate`
 *
 * Supported placeholders in each message:
 * - {LAST_CONFIRM_ISO}
 * - {CYCLE_START_ISO}
 * - {DEADLINE_ISO}
 * - {ACK_URL}
 */

declare(strict_types=1);

return [
'jane-doe' => [
'subject' => '[totmannschalter] Escalation triggered for Jane',
'body' => <<<TXT
Dear Jane,

If you are reading this, I have died.

I’m sorry to bring you this news by email, but it mattered to me to leave you a clear message in my own words. Please take a moment, and read the following when you feel able.

What I’d like you to do:
1. Please click the link to confirm you’ve received this message. This will stop any further reminder emails being sent: {ACK_URL}
2. Please contact [name] on [phone/email]. They will help coordinate everything.
3. Please locate the following items and keep them safe: [e. g., my will / important documents / keys / notes / seed phrases / banking folders], which are at [location].
4. If applicable, please take care of [e. g., my pet / my plants / my flat] until [name, phone] takes over.
5. Please do not share any sensitive details (especially documents, account information, or passwords) with anyone not named in my instructions.

My wishes:
- I would like [brief funeral / memorial preference, if any], and I would like you to inform: [names, phone numbers, email addresses].
- Regarding personal belongings, please ensure [high-level wish, e. g., “family photos go to…”].
- If there is anything of mine you are unsure about, please ask [name, phone, email] rather than guessing.

Thank you for being part of my life. If you’re able, I’d be grateful if you could let [name] know you’ve seen this.

With love,
[Your name]
TXT,
],

'john_doe' => [
'subject' => '[totmannschalter] Escalation triggered for John',
'body' => <<<TXT
Hello John,

I’m dead.

Bye.
TXT,
],
];
