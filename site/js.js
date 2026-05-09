var TOTMANN_LOCALES = {
  "en-US": {
    "meta": {
      "title": "[totman] A fully self-hosted dead man's switch for email",
      "description": "totman is a self-hosted dead man's switch with granular timing, HMAC-signed links, recipient-specific messages, private downloads, and operator-visible logs.",
      "ogTitle": "[totman] A fully self-hosted dead man's switch for email",
      "ogDescription": "Self-hosted check-ins, granular timing, HMAC-signed links, recipient-specific messages, private downloads, and clear runtime diagnostics.",
      "twitterTitle": "[totman] A fully self-hosted dead man's switch for email",
      "twitterDescription": "Self-hosted check-ins, granular timing, HMAC-signed links, recipient-specific messages, private downloads, and clear runtime diagnostics."
    },
    "nav": [
      {
        "href": "#why-totman",
        "label": "Why"
      },
      {
        "href": "#features",
        "label": "Features"
      },
      {
        "href": "#use-cases",
        "label": "Uses"
      },
      {
        "href": "#how-it-works",
        "label": "Flow"
      },
      {
        "href": "#summary",
        "label": "Summary"
      },
      {
        "href": "#roadmap",
        "label": "Roadmap"
      }
    ],
    "buttons": [
      {
        "href": "https://github.com/MacSteini/totmannschalter/releases/latest",
        "class": "button button-primary",
        "label": "Download"
      },
      {
        "href": "https://github.com/MacSteini/totmannschalter/blob/main/docs/Installation.md",
        "class": "button button-secondary",
        "label": "Installation"
      },
      {
        "href": "https://github.com/MacSteini/totmannschalter",
        "class": "button button-secondary",
        "label": "Repository"
      }
    ],
    "sections": [
      {
        "id": "why-totman",
        "kicker": "Why totman",
        "title": "Built for private, precise handover, not generic reminders.",
        "cards": [
          {
            "title": "Granular timing",
            "body": "Control the reminder interval, confirmation window, grace period, missed-cycle threshold, and ACK follow-up timing."
          },
          {
            "title": "Individual handover",
            "body": "Prepare different messages for different recipients instead of sending one generic broadcast to everyone."
          },
          {
            "title": "Signed actions",
            "body": "Confirmation, acknowledgment, and download URLs are HMAC-signed so important actions depend on valid tokens."
          },
          {
            "title": "Private downloads",
            "body": "Deliver selected files from outside the public web root, bind download links to the intended file, and make links single-use when needed."
          },
          {
            "title": "Visible diagnostics",
            "body": "Use granular logs, the built-in check command, clear failure states, and operator warnings to see problems before they matter."
          },
          {
            "title": "Self-hosted control",
            "body": "Keep the switch, recipient data, message content, and files on infrastructure you operate, without a service vendor in the middle."
          }
        ]
      },
      {
        "id": "features",
        "kicker": "Key features",
        "title": "The practical controls behind the handover.",
        "cards": [
          {
            "title": "Fully self-hosted",
            "body": "Runs from your own PHP and mail environment, so the operational setup stays under your control."
          },
          {
            "title": "You choose the rhythm",
            "body": "Set the check-in schedule and escalation thresholds that fit your life, instead of adapting yourself to a fixed cadence."
          },
          {
            "title": "Intentional confirmation",
            "body": "Opening an email is not enough on its own. You make a deliberate confirmation, which helps prevent accidental resets."
          },
          {
            "title": "Protected links",
            "body": "Confirmation, acknowledgment, and recipient links are signed so unsuitable or accidental requests do not trigger the real action."
          },
          {
            "title": "Grace before escalation",
            "body": "The system waits through your configured window and safety buffer before it treats silence as a real problem."
          },
          {
            "title": "Predefined contact list",
            "body": "The contact scope is fixed in advance, so the system does not improvise when it matters."
          },
          {
            "title": "Recipient-specific messages",
            "body": "Different recipients can receive the message content you prepared for them, instead of one shared generic note."
          },
          {
            "title": "Receipt confirmation",
            "body": "A recipient can confirm receipt so the same event does not keep generating unnecessary follow-up."
          },
          {
            "title": "Private file delivery",
            "body": "Optional private files can be included for selected recipients when they are part of the plan, without putting them in the public web tree."
          }
        ]
      },
      {
        "id": "use-cases",
        "kicker": "Why it matters",
        "title": "Where this can actually be useful.",
        "cards": [
          {
            "title": "Death-case handover",
            "body": "Useful if selected people should receive prepared instructions only after you have stopped confirming for long enough to indicate a serious final event."
          },
          {
            "title": "Crypto asset recovery",
            "body": "Useful if trusted people may need carefully prepared guidance for wallets, seed phrase locations, exchanges, or other crypto assets."
          },
          {
            "title": "Banking and financial overview",
            "body": "Useful if your family or executor may need a private overview of bank accounts, insurance contacts, regular payments, or financial paperwork."
          },
          {
            "title": "Digital estate access",
            "body": "Useful if important online accounts, domain names, servers, password-manager instructions, or other digital estate details must not disappear with you."
          },
          {
            "title": "Incapacity-specific plan",
            "body": "Useful if you want a separate, narrower setup for coma or long-term incapacity, without handing over everything meant only for the death case."
          },
          {
            "title": "Sensitive documents for trusted people",
            "body": "Useful if letters, account notes, contact lists, or private files should reach only the people you selected, and only when the switch actually fires."
          }
        ]
      }
    ],
    "steps": [
      {
        "title": "You receive a check-in email",
        "body": "totman sends you a regular email asking you to confirm that you are still there."
      },
      {
        "title": "You confirm and the routine continues",
        "body": "As long as you confirm in time, the cycle resets and the prepared handover stays untouched."
      },
      {
        "title": "A meaningful gap changes the state",
        "body": "If confirmations stop for long enough, totman treats that as a real absence rather than a single missed moment."
      },
      {
        "title": "The prepared handover goes out",
        "body": "The prepared recipient messages are sent individually, and extra follow-up can stop once receipt has been confirmed."
      }
    ],
    "summary": [
      {
        "title": "Quiet in normal life",
        "body": "When your regular routine is working, totman stays in the background instead of demanding constant attention."
      },
      {
        "title": "Prepared before it is needed",
        "body": "The timing, recipient list, individual messages, and optional files are all decided in advance, before there is any urgency."
      },
      {
        "title": "Problems do not stay hidden",
        "body": "If totman detects a setup or runtime problem, it can send a separate warning mail to your own address so you hear about it directly."
      },
      {
        "title": "Protected links, clear trail",
        "body": "Detailed logs help you review what happened, and confirmation, ACK, and download links are protected with HMAC-based signing."
      },
      {
        "title": "Personal rather than broadcast",
        "body": "Escalation messages go out one by one, which keeps the handover clearer and more private than a shared blast."
      },
      {
        "title": "Clear stop to follow-up",
        "body": "Once receipt has been confirmed for an event, the additional follow-up can end cleanly."
      }
    ],
    "roadmap": [
      {
        "title": "Interim safety handover",
        "body": "An optional middle step could let trusted people step in before final escalation happens."
      },
      {
        "title": "Flexible hosting options",
        "body": "Future work may examine whether a more restricted shared-hosting mode is viable at all."
      },
      {
        "title": "Mail handling upgrades",
        "body": "Planned ideas include richer mail priority controls and optional output-format choices."
      },
      {
        "title": "Operator convenience",
        "body": "A later browser-based management layer could make recipients and messages easier to maintain."
      },
      {
        "title": "Stronger recipient privacy",
        "body": "Further work may cover encrypted final messages and split-secret delivery models."
      },
      {
        "title": "Resilience and portability",
        "body": "Longer-term ideas include alternative implementations, failover setups, and storage changes."
      }
    ],
    "footer": {
      "github": "GitHub",
      "copyright": "MacSteini © 2026",
      "docs": "Documentation",
      "aria": "Footer"
    },
    "language": {
      "aria": "Language",
      "current": "Current language"
    },
    "brandAria": "totman homepage",
    "brandTag": "A dead man’s switch for email.",
    "navAria": "Primary",
    "eyebrow": "A fully self-hosted dead man’s switch for email",
    "h1": "Your message, delivered when needed most.",
    "lead": "totman turns a private handover plan into a precise, self-hosted routine. It checks in on your schedule, waits through your configured safety windows, and sends recipient-specific messages and files only when confirmations stop for long enough.",
    "showcaseAria": "totman showcase",
    "showcaseAlt": "Stacked preview of totman message pages.",
    "skip": "Skip to content",
    "stepsKicker": "How it works",
    "stepsTitle": "What happens in practice.",
    "summaryKicker": "Summary",
    "summaryTitle": "What totman actually does for you.",
    "roadmapKicker": "Roadmap",
    "roadmapTitle": "Where totman could go next."
  },
  "en-GB": {
    "meta": {
      "title": "[totman] A fully self-hosted dead man's switch for email",
      "description": "totman is a self-hosted dead man's switch with granular timing, HMAC-signed links, recipient-specific messages, private downloads, and operator-visible logs.",
      "ogTitle": "[totman] A fully self-hosted dead man's switch for email",
      "ogDescription": "Self-hosted check-ins, granular timing, HMAC-signed links, recipient-specific messages, private downloads, and clear runtime diagnostics.",
      "twitterTitle": "[totman] A fully self-hosted dead man's switch for email",
      "twitterDescription": "Self-hosted check-ins, granular timing, HMAC-signed links, recipient-specific messages, private downloads, and clear runtime diagnostics."
    },
    "nav": [
      {
        "href": "#why-totman",
        "label": "Why"
      },
      {
        "href": "#features",
        "label": "Features"
      },
      {
        "href": "#use-cases",
        "label": "Uses"
      },
      {
        "href": "#how-it-works",
        "label": "Flow"
      },
      {
        "href": "#summary",
        "label": "Summary"
      },
      {
        "href": "#roadmap",
        "label": "Roadmap"
      }
    ],
    "buttons": [
      {
        "href": "https://github.com/MacSteini/totmannschalter/releases/latest",
        "class": "button button-primary",
        "label": "Download"
      },
      {
        "href": "https://github.com/MacSteini/totmannschalter/blob/main/docs/Installation.md",
        "class": "button button-secondary",
        "label": "Installation"
      },
      {
        "href": "https://github.com/MacSteini/totmannschalter",
        "class": "button button-secondary",
        "label": "Repository"
      }
    ],
    "sections": [
      {
        "id": "why-totman",
        "kicker": "Why totman",
        "title": "Built for private, precise handover, not generic reminders.",
        "cards": [
          {
            "title": "Granular timing",
            "body": "Control the reminder interval, confirmation window, grace period, missed-cycle threshold, and ACK follow-up timing."
          },
          {
            "title": "Individual handover",
            "body": "Prepare different messages for different recipients instead of sending one generic broadcast to everyone."
          },
          {
            "title": "Signed actions",
            "body": "Confirmation, acknowledgement, and download URLs are HMAC-signed so important actions depend on valid tokens."
          },
          {
            "title": "Private downloads",
            "body": "Deliver selected files from outside the public web root, bind download links to the intended file, and make links single-use when needed."
          },
          {
            "title": "Visible diagnostics",
            "body": "Use granular logs, the built-in check command, clear failure states, and operator warnings to see problems before they matter."
          },
          {
            "title": "Self-hosted control",
            "body": "Keep the switch, recipient data, message content, and files on infrastructure you operate, without a service vendor in the middle."
          }
        ]
      },
      {
        "id": "features",
        "kicker": "Key features",
        "title": "The practical controls behind the handover.",
        "cards": [
          {
            "title": "Fully self-hosted",
            "body": "Runs from your own PHP and mail environment, so the operational setup stays under your control."
          },
          {
            "title": "You choose the rhythm",
            "body": "Set the check-in schedule and escalation thresholds that fit your life, instead of adapting yourself to a fixed cadence."
          },
          {
            "title": "Intentional confirmation",
            "body": "Opening an email is not enough on its own. You make a deliberate confirmation, which helps prevent accidental resets."
          },
          {
            "title": "Protected links",
            "body": "Confirmation, acknowledgement, and recipient links are signed so unsuitable or accidental requests do not trigger the real action."
          },
          {
            "title": "Grace before escalation",
            "body": "The system waits through your configured window and safety buffer before it treats silence as a real problem."
          },
          {
            "title": "Predefined contact list",
            "body": "The contact scope is fixed in advance, so the system does not improvise when it matters."
          },
          {
            "title": "Recipient-specific messages",
            "body": "Different recipients can receive the message content you prepared for them, instead of one shared generic note."
          },
          {
            "title": "Receipt confirmation",
            "body": "A recipient can confirm receipt so the same event does not keep generating unnecessary follow-up."
          },
          {
            "title": "Private file delivery",
            "body": "Optional private files can be included for selected recipients when they are part of the plan, without putting them in the public web tree."
          }
        ]
      },
      {
        "id": "use-cases",
        "kicker": "Why it matters",
        "title": "Where this can actually be useful.",
        "cards": [
          {
            "title": "Death-case handover",
            "body": "Useful if selected people should receive prepared instructions only after you have stopped confirming for long enough to indicate a serious final event."
          },
          {
            "title": "Crypto asset recovery",
            "body": "Useful if trusted people may need carefully prepared guidance for wallets, seed phrase locations, exchanges, or other crypto assets."
          },
          {
            "title": "Banking and financial overview",
            "body": "Useful if your family or executor may need a private overview of bank accounts, insurance contacts, regular payments, or financial paperwork."
          },
          {
            "title": "Digital estate access",
            "body": "Useful if important online accounts, domain names, servers, password-manager instructions, or other digital estate details must not disappear with you."
          },
          {
            "title": "Incapacity-specific plan",
            "body": "Useful if you want a separate, narrower setup for coma or long-term incapacity, without handing over everything meant only for the death case."
          },
          {
            "title": "Sensitive documents for trusted people",
            "body": "Useful if letters, account notes, contact lists, or private files should reach only the people you selected, and only when the switch actually fires."
          }
        ]
      }
    ],
    "steps": [
      {
        "title": "You receive a check-in email",
        "body": "totman sends you a regular email asking you to confirm that you are still there."
      },
      {
        "title": "You confirm and the routine continues",
        "body": "As long as you confirm in time, the cycle resets and the prepared handover stays untouched."
      },
      {
        "title": "A meaningful gap changes the state",
        "body": "If confirmations stop for long enough, totman treats that as a real absence rather than a single missed moment."
      },
      {
        "title": "The prepared handover goes out",
        "body": "The prepared recipient messages are sent individually, and extra follow-up can stop once receipt has been confirmed."
      }
    ],
    "summary": [
      {
        "title": "Quiet in normal life",
        "body": "When your regular routine is working, totman stays in the background instead of demanding constant attention."
      },
      {
        "title": "Prepared before it is needed",
        "body": "The timing, recipient list, individual messages, and optional files are all decided in advance, before there is any urgency."
      },
      {
        "title": "Problems do not stay hidden",
        "body": "If totman detects a setup or runtime problem, it can send a separate warning mail to your own address so you hear about it directly."
      },
      {
        "title": "Protected links, clear trail",
        "body": "Detailed logs help you review what happened, and confirmation, ACK, and download links are protected with HMAC-based signing."
      },
      {
        "title": "Personal rather than broadcast",
        "body": "Escalation messages go out one by one, which keeps the handover clearer and more private than a shared blast."
      },
      {
        "title": "Clear stop to follow-up",
        "body": "Once receipt has been confirmed for an event, the additional follow-up can end cleanly."
      }
    ],
    "roadmap": [
      {
        "title": "Interim safety handover",
        "body": "An optional middle step could let trusted people step in before final escalation happens."
      },
      {
        "title": "Flexible hosting options",
        "body": "Future work may examine whether a more restricted shared-hosting mode is viable at all."
      },
      {
        "title": "Mail handling upgrades",
        "body": "Planned ideas include richer mail priority controls and optional output-format choices."
      },
      {
        "title": "Operator convenience",
        "body": "A later browser-based management layer could make recipients and messages easier to maintain."
      },
      {
        "title": "Stronger recipient privacy",
        "body": "Further work may cover encrypted final messages and split-secret delivery models."
      },
      {
        "title": "Resilience and portability",
        "body": "Longer-term ideas include alternative implementations, failover setups, and storage changes."
      }
    ],
    "footer": {
      "github": "GitHub",
      "copyright": "MacSteini © 2026",
      "docs": "Documentation",
      "aria": "Footer"
    },
    "language": {
      "aria": "Language",
      "current": "Current language"
    },
    "brandAria": "totman homepage",
    "brandTag": "A dead man’s switch for email.",
    "navAria": "Primary",
    "eyebrow": "A fully self-hosted dead man’s switch for email",
    "h1": "Your message, delivered when needed most.",
    "lead": "totman turns a private handover plan into a precise, self-hosted routine. It checks in on your schedule, waits through your configured safety windows, and sends recipient-specific messages and files only when confirmations stop for long enough.",
    "showcaseAria": "totman showcase",
    "showcaseAlt": "Stacked preview of totman message pages.",
    "skip": "Skip to content",
    "stepsKicker": "How it works",
    "stepsTitle": "What happens in practice.",
    "summaryKicker": "Summary",
    "summaryTitle": "What totman actually does for you.",
    "roadmapKicker": "Roadmap",
    "roadmapTitle": "Where totman could go next."
  },
  "de-DE": {
    "meta": {
      "title": "[totman] Ein selbst gehosteter Totmannschalter für E-Mail",
      "description": "totman ist ein selbst gehosteter Totmannschalter mit granularer Zeitsteuerung, HMAC-signierten Links, empfängerbezogenen Nachrichten, privaten Downloads und sichtbaren Betreiber-Logs.",
      "ogTitle": "[totman] Ein selbst gehosteter Totmannschalter für E-Mail",
      "ogDescription": "Selbst gehostete Check-ins, granulare Zeitsteuerung, HMAC-signierte Links, empfängerbezogene Nachrichten, private Downloads und klare Laufzeitdiagnosen.",
      "twitterTitle": "[totman] Ein selbst gehosteter Totmannschalter für E-Mail",
      "twitterDescription": "Selbst gehostete Check-ins, granulare Zeitsteuerung, HMAC-signierte Links, empfängerbezogene Nachrichten, private Downloads und klare Laufzeitdiagnosen."
    },
    "nav": [
      {
        "href": "#why-totman",
        "label": "Warum"
      },
      {
        "href": "#features",
        "label": "Funktionen"
      },
      {
        "href": "#use-cases",
        "label": "Einsatz"
      },
      {
        "href": "#how-it-works",
        "label": "Ablauf"
      },
      {
        "href": "#summary",
        "label": "Fazit"
      },
      {
        "href": "#roadmap",
        "label": "Roadmap"
      }
    ],
    "buttons": [
      {
        "href": "https://github.com/MacSteini/totmannschalter/releases/latest",
        "class": "button button-primary",
        "label": "Download"
      },
      {
        "href": "https://github.com/MacSteini/totmannschalter/blob/main/docs/Installation.md",
        "class": "button button-secondary",
        "label": "Installation"
      },
      {
        "href": "https://github.com/MacSteini/totmannschalter",
        "class": "button button-secondary",
        "label": "Repository"
      }
    ],
    "sections": [
      {
        "id": "why-totman",
        "kicker": "Warum totman",
        "title": "Gebaut für private, präzise Übergaben, nicht für generische Erinnerungen.",
        "cards": [
          {
            "title": "Granulare Zeitsteuerung",
            "body": "Steuere Erinnerungsintervall, Bestätigungsfenster, Karenzzeit, Schwelle verpasster Zyklen und ACK-Folgeerinnerungen."
          },
          {
            "title": "Individuelle Übergabe",
            "body": "Bereite unterschiedliche Nachrichten für unterschiedliche Empfänger vor, statt eine generische Rundmail an alle zu senden."
          },
          {
            "title": "Signierte Aktionen",
            "body": "Bestätigungs-, Empfangs- und Download-URLs sind HMAC-signiert, damit wichtige Aktionen gültige Tokens benötigen."
          },
          {
            "title": "Private Downloads",
            "body": "Stelle ausgewählte Dateien außerhalb des öffentlichen Webroots bereit, binde Downloadlinks an die vorgesehene Datei und nutze bei Bedarf Einmallinks."
          },
          {
            "title": "Sichtbare Diagnose",
            "body": "Nutze granulare Logs, den eingebauten check-Befehl, klare Fehlerzustände und Betreiberwarnungen, um Probleme früh zu sehen."
          },
          {
            "title": "Selbst gehostete Kontrolle",
            "body": "Behalte Schalter, Empfängerdaten, Nachrichteninhalte und Dateien auf Infrastruktur, die du betreibst, ohne Dienstanbieter dazwischen."
          }
        ]
      },
      {
        "id": "features",
        "kicker": "Kernfunktionen",
        "title": "Die praktischen Kontrollen hinter der Übergabe.",
        "cards": [
          {
            "title": "Vollständig selbst gehostet",
            "body": "Läuft in deiner eigenen PHP- und Mail-Umgebung, damit der Betrieb unter deiner Kontrolle bleibt."
          },
          {
            "title": "Du bestimmst den Rhythmus",
            "body": "Lege Check-in-Zeitplan und Eskalationsschwellen passend zu deinem Leben fest, statt dich an einen festen Takt anzupassen."
          },
          {
            "title": "Bewusste Bestätigung",
            "body": "Eine geöffnete E-Mail reicht nicht aus. Du bestätigst aktiv, damit versehentliche Resets vermieden werden."
          },
          {
            "title": "Geschützte Links",
            "body": "Bestätigungs-, Empfangs- und Empfängerlinks sind signiert, damit ungeeignete oder zufällige Requests keine echte Aktion auslösen."
          },
          {
            "title": "Karenz vor Eskalation",
            "body": "Das System wartet durch dein konfiguriertes Fenster und den Sicherheitspuffer, bevor Schweigen als echtes Problem gilt."
          },
          {
            "title": "Vorab festgelegte Kontakte",
            "body": "Der Empfängerkreis ist vorher festgelegt, damit das System nicht improvisiert, wenn es darauf ankommt."
          },
          {
            "title": "Empfängerbezogene Nachrichten",
            "body": "Unterschiedliche Empfänger können genau die für sie vorbereiteten Inhalte erhalten, nicht eine gemeinsame Standardnotiz."
          },
          {
            "title": "Empfangsbestätigung",
            "body": "Ein Empfänger kann den Empfang bestätigen, damit dasselbe Ereignis keine unnötigen Folge-E-Mails erzeugt."
          },
          {
            "title": "Private Dateiübergabe",
            "body": "Optionale private Dateien können ausgewählten Empfängern zugeordnet werden, ohne sie in den öffentlichen Webbaum zu legen."
          }
        ]
      },
      {
        "id": "use-cases",
        "kicker": "Warum das zählt",
        "title": "Wofür das tatsächlich nützlich sein kann.",
        "cards": [
          {
            "title": "Übergabe im Todesfall",
            "body": "Nützlich, wenn ausgewählte Personen vorbereitete Hinweise erst erhalten sollen, nachdem du lange genug nicht mehr bestätigt hast."
          },
          {
            "title": "Wiederherstellung von Crypto-Assets",
            "body": "Nützlich, wenn Vertrauenspersonen vorbereitete Hinweise zu Wallets, Seed-Phrase-Orten, Börsen oder anderen Crypto-Assets benötigen könnten."
          },
          {
            "title": "Banking und Finanzüberblick",
            "body": "Nützlich, wenn Familie oder Nachlassverwalter einen privaten Überblick über Konten, Versicherungen, laufende Zahlungen oder Finanzunterlagen brauchen könnten."
          },
          {
            "title": "Digitaler Nachlasszugang",
            "body": "Nützlich, wenn wichtige Online-Konten, Domains, Server, Passwortmanager-Hinweise oder andere digitale Nachlassdaten nicht verschwinden dürfen."
          },
          {
            "title": "Plan für Handlungsunfähigkeit",
            "body": "Nützlich, wenn du für Koma oder langfristige Handlungsunfähigkeit einen engeren Plan willst, ohne alles aus dem Todesfall-Setup zu übergeben."
          },
          {
            "title": "Sensible Dokumente für Vertrauenspersonen",
            "body": "Nützlich, wenn Briefe, Kontohinweise, Kontaktlisten oder private Dateien nur ausgewählte Personen erreichen sollen, und nur wenn der Schalter auslöst."
          }
        ]
      }
    ],
    "steps": [
      {
        "title": "Du erhältst eine Check-in-E-Mail",
        "body": "totman sendet dir regelmäßig eine E-Mail mit der Bitte zu bestätigen, dass du noch da bist."
      },
      {
        "title": "Du bestätigst und die Routine läuft weiter",
        "body": "Solange du rechtzeitig bestätigst, wird der Zyklus zurückgesetzt und die vorbereitete Übergabe bleibt unberührt."
      },
      {
        "title": "Eine relevante Lücke ändert den Zustand",
        "body": "Wenn Bestätigungen lange genug ausbleiben, behandelt totman das als echte Abwesenheit und nicht als einzelnen verpassten Moment."
      },
      {
        "title": "Die vorbereitete Übergabe wird versendet",
        "body": "Die vorbereiteten Empfängernachrichten werden einzeln versendet; zusätzliche Folge-E-Mails können nach bestätigtem Empfang enden."
      }
    ],
    "summary": [
      {
        "title": "Ruhig im normalen Leben",
        "body": "Wenn deine Routine funktioniert, bleibt totman im Hintergrund, statt ständig Aufmerksamkeit zu verlangen."
      },
      {
        "title": "Vorbereitet, bevor es nötig ist",
        "body": "Timing, Empfängerliste, individuelle Nachrichten und optionale Dateien werden im Voraus festgelegt, bevor Dringlichkeit entsteht."
      },
      {
        "title": "Probleme bleiben nicht verborgen",
        "body": "Wenn totman ein Setup- oder Laufzeitproblem erkennt, kann eine separate Warnmail an deine eigene Adresse gehen."
      },
      {
        "title": "Geschützte Links, klare Spur",
        "body": "Detaillierte Logs helfen bei der Nachvollziehbarkeit; Bestätigungs-, ACK- und Downloadlinks sind mit HMAC-Signaturen geschützt."
      },
      {
        "title": "Persönlich statt Rundmail",
        "body": "Eskalationsnachrichten gehen einzeln raus, wodurch die Übergabe klarer und privater bleibt als bei einer gemeinsamen Rundmail."
      },
      {
        "title": "Klares Ende für Nachfassmails",
        "body": "Sobald der Empfang für ein Ereignis bestätigt wurde, können zusätzliche Folge-E-Mails sauber enden."
      }
    ],
    "roadmap": [
      {
        "title": "Zwischenstufe für Sicherheit",
        "body": "Eine optionale Zwischenstufe könnte Vertrauenspersonen eingreifen lassen, bevor die finale Eskalation passiert."
      },
      {
        "title": "Flexible Hosting-Optionen",
        "body": "Künftige Arbeit kann prüfen, ob ein stärker eingeschränkter Shared-Hosting-Modus überhaupt tragfähig ist."
      },
      {
        "title": "Ausbau der Mailsteuerung",
        "body": "Geplante Ideen umfassen feinere Prioritätssteuerung und optionale Ausgabeformate."
      },
      {
        "title": "Komfort für Betreiber",
        "body": "Eine spätere Browser-Verwaltung könnte Empfänger und Nachrichten einfacher pflegbar machen."
      },
      {
        "title": "Stärkere Empfänger-Privatsphäre",
        "body": "Weitere Arbeit kann verschlüsselte Abschlussnachrichten und Split-Secret-Zustellung abdecken."
      },
      {
        "title": "Resilienz und Portabilität",
        "body": "Langfristige Ideen umfassen alternative Implementierungen, Failover-Setups und Änderungen an der Speicherung."
      }
    ],
    "footer": {
      "github": "GitHub",
      "copyright": "MacSteini © 2026",
      "docs": "Dokumentation",
      "aria": "Footer"
    },
    "language": {
      "aria": "Sprache",
      "current": "Aktuelle Sprache"
    },
    "brandAria": "totman-Startseite",
    "brandTag": "Ein Totmannschalter für E-Mail.",
    "navAria": "Hauptnavigation",
    "eyebrow": "Ein vollständig selbst gehosteter Totmannschalter für E-Mail",
    "h1": "Deine Nachricht, zugestellt wenn es darauf ankommt.",
    "lead": "totman macht aus einem privaten Übergabeplan eine präzise, selbst gehostete Routine. Der Dienst fragt nach deinem Zeitplan nach, wartet durch die konfigurierten Sicherheitsfenster und sendet empfängerbezogene Nachrichten und Dateien erst, wenn Bestätigungen lange genug ausbleiben.",
    "showcaseAria": "totman-Vorschau",
    "showcaseAlt": "Gestapelte Vorschau von totman-Nachrichtenseiten.",
    "skip": "Zum Inhalt springen",
    "stepsKicker": "Ablauf",
    "stepsTitle": "Was praktisch passiert.",
    "summaryKicker": "Fazit",
    "summaryTitle": "Was totman konkret für dich tut.",
    "roadmapKicker": "Roadmap",
    "roadmapTitle": "Wohin totman sich entwickeln könnte."
  },
  "es-ES": {
    "meta": {
      "title": "[totman] Un interruptor de hombre muerto autohospedado para correo electrónico",
      "description": "totman es un interruptor de hombre muerto autohospedado con tiempos granulares, enlaces firmados con HMAC, mensajes por destinatario, descargas privadas y registros visibles para el operador.",
      "ogTitle": "[totman] Un interruptor de hombre muerto autohospedado para correo electrónico",
      "ogDescription": "Check-ins autohospedados, tiempos granulares, enlaces firmados con HMAC, mensajes por destinatario, descargas privadas y diagnósticos claros de ejecución.",
      "twitterTitle": "[totman] Un interruptor de hombre muerto autohospedado para correo electrónico",
      "twitterDescription": "Check-ins autohospedados, tiempos granulares, enlaces firmados con HMAC, mensajes por destinatario, descargas privadas y diagnósticos claros de ejecución."
    },
    "nav": [
      {
        "href": "#why-totman",
        "label": "Porqué"
      },
      {
        "href": "#features",
        "label": "Funciones"
      },
      {
        "href": "#use-cases",
        "label": "Usos"
      },
      {
        "href": "#how-it-works",
        "label": "Flujo"
      },
      {
        "href": "#summary",
        "label": "Resumen"
      },
      {
        "href": "#roadmap",
        "label": "Roadmap"
      }
    ],
    "buttons": [
      {
        "href": "https://github.com/MacSteini/totmannschalter/releases/latest",
        "class": "button button-primary",
        "label": "Descargar"
      },
      {
        "href": "https://github.com/MacSteini/totmannschalter/blob/main/docs/Installation.md",
        "class": "button button-secondary",
        "label": "Instalación"
      },
      {
        "href": "https://github.com/MacSteini/totmannschalter",
        "class": "button button-secondary",
        "label": "Repositorio"
      }
    ],
    "sections": [
      {
        "id": "why-totman",
        "kicker": "Por qué totman",
        "title": "Diseñado para entregas privadas y precisas, no para recordatorios genéricos.",
        "cards": [
          {
            "title": "Tiempos granulares",
            "body": "Controla el intervalo de recordatorios, la ventana de confirmación, el periodo de gracia, el umbral de ciclos perdidos y los recordatorios ACK."
          },
          {
            "title": "Entrega individual",
            "body": "Prepara mensajes distintos para destinatarios distintos en lugar de enviar una comunicación genérica a todos."
          },
          {
            "title": "Acciones firmadas",
            "body": "Las URL de confirmación, acuse y descarga están firmadas con HMAC para que las acciones importantes dependan de tokens válidos."
          },
          {
            "title": "Descargas privadas",
            "body": "Entrega archivos seleccionados desde fuera del árbol web público, vincula los enlaces a su archivo previsto y usa enlaces de un solo uso cuando haga falta."
          },
          {
            "title": "Diagnóstico visible",
            "body": "Usa registros granulares, el comando check integrado, estados de fallo claros y avisos al operador para ver problemas a tiempo."
          },
          {
            "title": "Control autohospedado",
            "body": "Mantén el interruptor, los datos de destinatarios, el contenido de mensajes y los archivos en infraestructura que operas, sin un proveedor de servicio en medio."
          }
        ]
      },
      {
        "id": "features",
        "kicker": "Funciones clave",
        "title": "Los controles prácticos detrás de la entrega.",
        "cards": [
          {
            "title": "Totalmente autohospedado",
            "body": "Se ejecuta en tu propio entorno PHP y de correo, de modo que la operación queda bajo tu control."
          },
          {
            "title": "Tú eliges el ritmo",
            "body": "Define el calendario de check-in y los umbrales de escalada que encajan con tu vida, no con una cadencia fija."
          },
          {
            "title": "Confirmación deliberada",
            "body": "Abrir un correo no basta. Haces una confirmación explícita, lo que ayuda a evitar reinicios accidentales."
          },
          {
            "title": "Enlaces protegidos",
            "body": "Los enlaces de confirmación, acuse y destinatario están firmados para que solicitudes inadecuadas o accidentales no ejecuten la acción real."
          },
          {
            "title": "Gracia antes de escalar",
            "body": "El sistema espera durante tu ventana configurada y el margen de seguridad antes de tratar el silencio como un problema real."
          },
          {
            "title": "Lista de contactos definida",
            "body": "El alcance de contactos queda fijado de antemano, para que el sistema no improvise cuando importa."
          },
          {
            "title": "Mensajes por destinatario",
            "body": "Cada destinatario puede recibir el contenido preparado para esa persona, no una nota genérica compartida."
          },
          {
            "title": "Confirmación de recepción",
            "body": "Un destinatario puede confirmar la recepción para que el mismo evento no siga generando seguimientos innecesarios."
          },
          {
            "title": "Entrega privada de archivos",
            "body": "Los archivos privados opcionales pueden incluirse para destinatarios seleccionados sin colocarlos en el árbol web público."
          }
        ]
      },
      {
        "id": "use-cases",
        "kicker": "Por qué importa",
        "title": "Dónde puede ser realmente útil.",
        "cards": [
          {
            "title": "Entrega en caso de fallecimiento",
            "body": "Útil si personas seleccionadas deben recibir instrucciones preparadas solo después de que hayas dejado de confirmar durante el tiempo suficiente."
          },
          {
            "title": "Recuperación de criptoactivos",
            "body": "Útil si personas de confianza pueden necesitar guía preparada sobre wallets, ubicaciones de seed phrases, exchanges u otros criptoactivos."
          },
          {
            "title": "Resumen bancario y financiero",
            "body": "Útil si tu familia o albacea puede necesitar una vista privada de cuentas bancarias, seguros, pagos periódicos o documentación financiera."
          },
          {
            "title": "Acceso al patrimonio digital",
            "body": "Útil si cuentas online, dominios, servidores, instrucciones de gestor de contraseñas u otros detalles digitales importantes no deben desaparecer contigo."
          },
          {
            "title": "Plan para incapacidad",
            "body": "Útil si quieres una configuración separada y más estrecha para coma o incapacidad prolongada, sin entregar todo lo reservado para el fallecimiento."
          },
          {
            "title": "Documentos sensibles para personas de confianza",
            "body": "Útil si cartas, notas de cuentas, listas de contactos o archivos privados deben llegar solo a las personas elegidas y solo cuando el interruptor se active."
          }
        ]
      }
    ],
    "steps": [
      {
        "title": "Recibes un correo de check-in",
        "body": "totman te envía un correo regular para pedirte que confirmes que sigues ahí."
      },
      {
        "title": "Confirmas y la rutina continúa",
        "body": "Mientras confirmes a tiempo, el ciclo se reinicia y la entrega preparada queda intacta."
      },
      {
        "title": "Una ausencia significativa cambia el estado",
        "body": "Si las confirmaciones se detienen durante el tiempo suficiente, totman lo trata como una ausencia real y no como un momento aislado."
      },
      {
        "title": "La entrega preparada se envía",
        "body": "Los mensajes preparados se envían individualmente, y el seguimiento adicional puede terminar cuando se confirma la recepción."
      }
    ],
    "summary": [
      {
        "title": "Silencioso en la vida normal",
        "body": "Cuando tu rutina funciona, totman permanece en segundo plano en lugar de exigir atención constante."
      },
      {
        "title": "Preparado antes de necesitarse",
        "body": "Los tiempos, destinatarios, mensajes individuales y archivos opcionales se deciden de antemano, antes de que haya urgencia."
      },
      {
        "title": "Los problemas no quedan ocultos",
        "body": "Si totman detecta un problema de configuración o ejecución, puede enviar un aviso separado a tu propia dirección."
      },
      {
        "title": "Enlaces protegidos, rastro claro",
        "body": "Los registros detallados ayudan a revisar lo ocurrido; los enlaces de confirmación, ACK y descarga están protegidos con firmas HMAC."
      },
      {
        "title": "Personal, no masivo",
        "body": "Los mensajes de escalada se envían uno por uno, lo que mantiene la entrega más clara y privada que un envío común."
      },
      {
        "title": "Fin claro del seguimiento",
        "body": "Una vez confirmada la recepción de un evento, el seguimiento adicional puede terminar limpiamente."
      }
    ],
    "roadmap": [
      {
        "title": "Entrega provisional de seguridad",
        "body": "Un paso intermedio opcional podría permitir que personas de confianza intervengan antes de la escalada final."
      },
      {
        "title": "Opciones de hosting flexibles",
        "body": "El trabajo futuro puede estudiar si un modo de hosting compartido más restringido es viable."
      },
      {
        "title": "Mejoras de correo",
        "body": "Las ideas previstas incluyen controles de prioridad más ricos y opciones de formato de salida."
      },
      {
        "title": "Comodidad para el operador",
        "body": "Una capa posterior de gestión en navegador podría facilitar el mantenimiento de destinatarios y mensajes."
      },
      {
        "title": "Mayor privacidad del destinatario",
        "body": "El trabajo futuro puede cubrir mensajes finales cifrados y modelos de entrega con secreto dividido."
      },
      {
        "title": "Resiliencia y portabilidad",
        "body": "Las ideas a largo plazo incluyen implementaciones alternativas, configuraciones de failover y cambios de almacenamiento."
      }
    ],
    "footer": {
      "github": "GitHub",
      "copyright": "MacSteini © 2026",
      "docs": "Documentación",
      "aria": "Pie de página"
    },
    "language": {
      "aria": "Idioma",
      "current": "Idioma actual"
    },
    "brandAria": "Página de inicio de totman",
    "brandTag": "Un interruptor de hombre muerto para correo electrónico.",
    "navAria": "Principal",
    "eyebrow": "Un interruptor de hombre muerto totalmente autohospedado para correo electrónico",
    "h1": "Tu mensaje, entregado cuando más importa.",
    "lead": "totman convierte un plan privado de entrega en una rutina precisa y autohospedada. Hace check-in según tu calendario, espera durante las ventanas de seguridad configuradas y envía mensajes y archivos específicos para cada destinatario solo cuando las confirmaciones dejan de llegar durante el tiempo suficiente.",
    "showcaseAria": "Vista previa de totman",
    "showcaseAlt": "Vista previa apilada de páginas de mensajes de totman.",
    "skip": "Saltar al contenido",
    "stepsKicker": "Flujo",
    "stepsTitle": "Qué ocurre en la práctica.",
    "summaryKicker": "Resumen",
    "summaryTitle": "Qué hace realmente totman por ti.",
    "roadmapKicker": "Roadmap",
    "roadmapTitle": "Hacia dónde podría avanzar totman."
  },
  "fr-FR": {
    "meta": {
      "title": "[totman] Un dispositif homme mort autohébergé pour le courriel",
      "description": "totman est un dispositif homme mort autohébergé avec calendrier granulaire, liens signés HMAC, messages par destinataire, téléchargements privés et journaux visibles par l’opérateur.",
      "ogTitle": "[totman] Un dispositif homme mort autohébergé pour le courriel",
      "ogDescription": "Check-ins autohébergés, calendrier granulaire, liens signés HMAC, messages par destinataire, téléchargements privés et diagnostics d’exécution clairs.",
      "twitterTitle": "[totman] Un dispositif homme mort autohébergé pour le courriel",
      "twitterDescription": "Check-ins autohébergés, calendrier granulaire, liens signés HMAC, messages par destinataire, téléchargements privés et diagnostics d’exécution clairs."
    },
    "nav": [
      {
        "href": "#why-totman",
        "label": "Pourquoi"
      },
      {
        "href": "#features",
        "label": "Fonctions"
      },
      {
        "href": "#use-cases",
        "label": "Usages"
      },
      {
        "href": "#how-it-works",
        "label": "Flux"
      },
      {
        "href": "#summary",
        "label": "Résumé"
      },
      {
        "href": "#roadmap",
        "label": "Roadmap"
      }
    ],
    "buttons": [
      {
        "href": "https://github.com/MacSteini/totmannschalter/releases/latest",
        "class": "button button-primary",
        "label": "Télécharger"
      },
      {
        "href": "https://github.com/MacSteini/totmannschalter/blob/main/docs/Installation.md",
        "class": "button button-secondary",
        "label": "Installation"
      },
      {
        "href": "https://github.com/MacSteini/totmannschalter",
        "class": "button button-secondary",
        "label": "Dépôt"
      }
    ],
    "sections": [
      {
        "id": "why-totman",
        "kicker": "Pourquoi totman",
        "title": "Conçu pour une transmission privée et précise, pas pour de simples rappels génériques.",
        "cards": [
          {
            "title": "Calendrier granulaire",
            "body": "Contrôlez l’intervalle de rappel, la fenêtre de confirmation, la période de grâce, le seuil de cycles manqués et les rappels ACK."
          },
          {
            "title": "Transmission individuelle",
            "body": "Préparez des messages différents pour des destinataires différents, au lieu d’envoyer une note générique à tous."
          },
          {
            "title": "Actions signées",
            "body": "Les URL de confirmation, d’accusé et de téléchargement sont signées HMAC afin que les actions importantes dépendent de jetons valides."
          },
          {
            "title": "Téléchargements privés",
            "body": "Distribuez certains fichiers hors de la racine web publique, liez les liens au fichier prévu et utilisez des liens à usage unique si nécessaire."
          },
          {
            "title": "Diagnostics visibles",
            "body": "Utilisez des journaux granulaires, la commande check intégrée, des états d’échec clairs et des avertissements opérateur pour voir les problèmes tôt."
          },
          {
            "title": "Contrôle autohébergé",
            "body": "Gardez le dispositif, les données de destinataires, le contenu des messages et les fichiers sur une infrastructure que vous exploitez, sans fournisseur de service intermédiaire."
          }
        ]
      },
      {
        "id": "features",
        "kicker": "Fonctions clés",
        "title": "Les contrôles pratiques derrière la transmission.",
        "cards": [
          {
            "title": "Entièrement autohébergé",
            "body": "Fonctionne dans votre propre environnement PHP et courriel, pour que l’exploitation reste sous votre contrôle."
          },
          {
            "title": "Vous choisissez le rythme",
            "body": "Définissez le calendrier de check-in et les seuils d’escalade adaptés à votre vie, sans vous plier à une cadence fixe."
          },
          {
            "title": "Confirmation volontaire",
            "body": "Ouvrir un courriel ne suffit pas. Vous confirmez explicitement, ce qui aide à éviter les réinitialisations accidentelles."
          },
          {
            "title": "Liens protégés",
            "body": "Les liens de confirmation, d’accusé et de destinataire sont signés afin que les requêtes inadaptées ou accidentelles ne déclenchent pas l’action réelle."
          },
          {
            "title": "Grâce avant escalade",
            "body": "Le système attend pendant votre fenêtre configurée et votre marge de sécurité avant de considérer le silence comme un vrai problème."
          },
          {
            "title": "Liste de contacts prédéfinie",
            "body": "Le périmètre de contacts est fixé à l’avance, afin que le système n’improvise pas au moment critique."
          },
          {
            "title": "Messages par destinataire",
            "body": "Chaque destinataire peut recevoir le contenu préparé pour lui, plutôt qu’une note générique commune."
          },
          {
            "title": "Confirmation de réception",
            "body": "Un destinataire peut confirmer la réception afin que le même événement ne génère pas de relances inutiles."
          },
          {
            "title": "Livraison privée de fichiers",
            "body": "Des fichiers privés optionnels peuvent être ajoutés pour certains destinataires sans les placer dans l’arborescence web publique."
          }
        ]
      },
      {
        "id": "use-cases",
        "kicker": "Pourquoi c’est utile",
        "title": "Où cela peut réellement servir.",
        "cards": [
          {
            "title": "Transmission en cas de décès",
            "body": "Utile si certaines personnes doivent recevoir des instructions préparées seulement après une absence de confirmation suffisamment longue."
          },
          {
            "title": "Récupération de cryptoactifs",
            "body": "Utile si des personnes de confiance peuvent avoir besoin d’indications préparées sur des wallets, emplacements de seed phrases, plateformes ou autres cryptoactifs."
          },
          {
            "title": "Vue bancaire et financière",
            "body": "Utile si votre famille ou exécuteur peut avoir besoin d’une vue privée des comptes bancaires, assurances, paiements réguliers ou documents financiers."
          },
          {
            "title": "Accès au patrimoine numérique",
            "body": "Utile si des comptes en ligne, noms de domaine, serveurs, consignes de gestionnaire de mots de passe ou autres éléments numériques importants ne doivent pas disparaître avec vous."
          },
          {
            "title": "Plan d’incapacité",
            "body": "Utile si vous voulez une configuration séparée et plus restreinte pour le coma ou l’incapacité durable, sans transmettre tout ce qui relève seulement du décès."
          },
          {
            "title": "Documents sensibles pour personnes de confiance",
            "body": "Utile si lettres, notes de comptes, listes de contacts ou fichiers privés doivent atteindre seulement les personnes choisies, et seulement quand le dispositif se déclenche."
          }
        ]
      }
    ],
    "steps": [
      {
        "title": "Vous recevez un courriel de check-in",
        "body": "totman vous envoie régulièrement un courriel pour vous demander de confirmer que vous êtes toujours là."
      },
      {
        "title": "Vous confirmez et la routine continue",
        "body": "Tant que vous confirmez à temps, le cycle est réinitialisé et la transmission préparée reste intacte."
      },
      {
        "title": "Une absence significative change l’état",
        "body": "Si les confirmations cessent assez longtemps, totman traite cela comme une absence réelle plutôt que comme un simple moment manqué."
      },
      {
        "title": "La transmission préparée part",
        "body": "Les messages préparés sont envoyés individuellement, et les relances supplémentaires peuvent cesser lorsque la réception est confirmée."
      }
    ],
    "summary": [
      {
        "title": "Discret au quotidien",
        "body": "Lorsque votre routine fonctionne, totman reste en arrière-plan au lieu de demander une attention constante."
      },
      {
        "title": "Préparé avant l’urgence",
        "body": "Le calendrier, la liste de destinataires, les messages individuels et les fichiers optionnels sont décidés à l’avance."
      },
      {
        "title": "Les problèmes ne restent pas cachés",
        "body": "Si totman détecte un problème de configuration ou d’exécution, il peut envoyer un avertissement séparé à votre propre adresse."
      },
      {
        "title": "Liens protégés, trace claire",
        "body": "Des journaux détaillés aident à revoir ce qui s’est passé; les liens de confirmation, ACK et téléchargement sont protégés par signature HMAC."
      },
      {
        "title": "Personnel plutôt que collectif",
        "body": "Les messages d’escalade partent un par un, ce qui rend la transmission plus claire et plus privée qu’un envoi commun."
      },
      {
        "title": "Fin nette des relances",
        "body": "Une fois la réception confirmée pour un événement, les relances supplémentaires peuvent se terminer proprement."
      }
    ],
    "roadmap": [
      {
        "title": "Transmission de sécurité intermédiaire",
        "body": "Une étape intermédiaire optionnelle pourrait permettre à des personnes de confiance d’intervenir avant l’escalade finale."
      },
      {
        "title": "Options d’hébergement flexibles",
        "body": "Des travaux futurs pourront examiner si un mode d’hébergement mutualisé plus restreint est viable."
      },
      {
        "title": "Améliorations courriel",
        "body": "Les idées prévues incluent des contrôles de priorité plus riches et des choix optionnels de format de sortie."
      },
      {
        "title": "Confort opérateur",
        "body": "Une future couche de gestion dans le navigateur pourrait faciliter la maintenance des destinataires et messages."
      },
      {
        "title": "Confidentialité destinataire renforcée",
        "body": "Des travaux ultérieurs pourront couvrir les messages finaux chiffrés et des modèles de secret partagé."
      },
      {
        "title": "Résilience et portabilité",
        "body": "Les idées à long terme incluent d’autres implémentations, des configurations de bascule et des changements de stockage."
      }
    ],
    "footer": {
      "github": "GitHub",
      "copyright": "MacSteini © 2026",
      "docs": "Documentation",
      "aria": "Pied de page"
    },
    "language": {
      "aria": "Langue",
      "current": "Langue actuelle"
    },
    "brandAria": "Page d’accueil totman",
    "brandTag": "Un dispositif homme mort pour le courriel.",
    "navAria": "Principale",
    "eyebrow": "Un dispositif homme mort entièrement autohébergé pour le courriel",
    "h1": "Votre message, livré au moment décisif.",
    "lead": "totman transforme un plan de transmission privé en routine précise et autohébergée. Il effectue les check-ins selon votre calendrier, respecte vos fenêtres de sécurité configurées et n’envoie les messages et fichiers propres à chaque destinataire que lorsque les confirmations cessent assez longtemps.",
    "showcaseAria": "Aperçu de totman",
    "showcaseAlt": "Aperçu empilé de pages de messages totman.",
    "skip": "Aller au contenu",
    "stepsKicker": "Flux",
    "stepsTitle": "Ce qui se passe en pratique.",
    "summaryKicker": "Résumé",
    "summaryTitle": "Ce que totman fait réellement pour vous.",
    "roadmapKicker": "Roadmap",
    "roadmapTitle": "Vers où totman pourrait évoluer."
  },
  "it-IT": {
    "meta": {
      "title": "[totman] Un interruttore uomo morto autogestito per email",
      "description": "totman è un interruttore uomo morto autogestito con tempistiche granulari, link firmati HMAC, messaggi per destinatario, download privati e log visibili all’operatore.",
      "ogTitle": "[totman] Un interruttore uomo morto autogestito per email",
      "ogDescription": "Check-in autogestiti, tempistiche granulari, link firmati HMAC, messaggi per destinatario, download privati e diagnostica runtime chiara.",
      "twitterTitle": "[totman] Un interruttore uomo morto autogestito per email",
      "twitterDescription": "Check-in autogestiti, tempistiche granulari, link firmati HMAC, messaggi per destinatario, download privati e diagnostica runtime chiara."
    },
    "nav": [
      {
        "href": "#why-totman",
        "label": "Perché"
      },
      {
        "href": "#features",
        "label": "Funzioni"
      },
      {
        "href": "#use-cases",
        "label": "Usi"
      },
      {
        "href": "#how-it-works",
        "label": "Flusso"
      },
      {
        "href": "#summary",
        "label": "Sintesi"
      },
      {
        "href": "#roadmap",
        "label": "Roadmap"
      }
    ],
    "buttons": [
      {
        "href": "https://github.com/MacSteini/totmannschalter/releases/latest",
        "class": "button button-primary",
        "label": "Download"
      },
      {
        "href": "https://github.com/MacSteini/totmannschalter/blob/main/docs/Installation.md",
        "class": "button button-secondary",
        "label": "Installazione"
      },
      {
        "href": "https://github.com/MacSteini/totmannschalter",
        "class": "button button-secondary",
        "label": "Repository"
      }
    ],
    "sections": [
      {
        "id": "why-totman",
        "kicker": "Perché totman",
        "title": "Pensato per consegne private e precise, non per promemoria generici.",
        "cards": [
          {
            "title": "Tempistiche granulari",
            "body": "Controlla intervallo dei promemoria, finestra di conferma, periodo di grazia, soglia dei cicli mancati e promemoria ACK."
          },
          {
            "title": "Consegna individuale",
            "body": "Prepara messaggi diversi per destinatari diversi invece di inviare una comunicazione generica a tutti."
          },
          {
            "title": "Azioni firmate",
            "body": "Gli URL di conferma, ACK e download sono firmati HMAC, così le azioni importanti dipendono da token validi."
          },
          {
            "title": "Download privati",
            "body": "Distribuisci file selezionati fuori dalla web root pubblica, lega i link al file previsto e usa link monouso quando serve."
          },
          {
            "title": "Diagnostica visibile",
            "body": "Usa log granulari, il comando check integrato, stati di errore chiari e avvisi all’operatore per vedere i problemi in anticipo."
          },
          {
            "title": "Controllo autogestito",
            "body": "Mantieni interruttore, dati dei destinatari, contenuto dei messaggi e file su infrastruttura che gestisci, senza un fornitore di servizio in mezzo."
          }
        ]
      },
      {
        "id": "features",
        "kicker": "Funzioni chiave",
        "title": "I controlli pratici dietro la consegna.",
        "cards": [
          {
            "title": "Completamente autogestito",
            "body": "Funziona nel tuo ambiente PHP e mail, così l’operatività resta sotto il tuo controllo."
          },
          {
            "title": "Scegli tu il ritmo",
            "body": "Definisci calendario di check-in e soglie di escalation adatti alla tua vita, non a una cadenza fissa."
          },
          {
            "title": "Conferma intenzionale",
            "body": "Aprire un’email non basta. Effettui una conferma esplicita, aiutando a prevenire reset accidentali."
          },
          {
            "title": "Link protetti",
            "body": "I link di conferma, ACK e destinatario sono firmati, così richieste inadatte o accidentali non attivano l’azione reale."
          },
          {
            "title": "Grazia prima dell’escalation",
            "body": "Il sistema attende la finestra configurata e il margine di sicurezza prima di trattare il silenzio come un problema reale."
          },
          {
            "title": "Lista contatti predefinita",
            "body": "L’ambito dei contatti è fissato in anticipo, così il sistema non improvvisa quando conta."
          },
          {
            "title": "Messaggi per destinatario",
            "body": "Destinatari diversi possono ricevere il contenuto preparato per loro, non una nota generica condivisa."
          },
          {
            "title": "Conferma di ricezione",
            "body": "Un destinatario può confermare la ricezione, così lo stesso evento non genera follow-up inutili."
          },
          {
            "title": "Consegna privata di file",
            "body": "File privati opzionali possono essere inclusi per destinatari selezionati senza collocarli nell’albero web pubblico."
          }
        ]
      },
      {
        "id": "use-cases",
        "kicker": "Perché conta",
        "title": "Dove può essere davvero utile.",
        "cards": [
          {
            "title": "Consegna in caso di morte",
            "body": "Utile se persone selezionate devono ricevere istruzioni preparate solo dopo che hai smesso di confermare abbastanza a lungo."
          },
          {
            "title": "Recupero di crypto asset",
            "body": "Utile se persone fidate potrebbero aver bisogno di indicazioni preparate per wallet, posizioni di seed phrase, exchange o altri crypto asset."
          },
          {
            "title": "Panoramica bancaria e finanziaria",
            "body": "Utile se famiglia o esecutore potrebbero aver bisogno di una panoramica privata di conti, assicurazioni, pagamenti ricorrenti o documenti finanziari."
          },
          {
            "title": "Accesso al patrimonio digitale",
            "body": "Utile se account online, domini, server, istruzioni per password manager o altri dettagli digitali importanti non devono scomparire con te."
          },
          {
            "title": "Piano per incapacità",
            "body": "Utile se vuoi una configurazione separata e più stretta per coma o incapacità prolungata, senza consegnare tutto ciò che riguarda solo il decesso."
          },
          {
            "title": "Documenti sensibili per persone fidate",
            "body": "Utile se lettere, note su account, liste di contatti o file privati devono raggiungere solo le persone scelte e solo quando l’interruttore scatta."
          }
        ]
      }
    ],
    "steps": [
      {
        "title": "Ricevi un’email di check-in",
        "body": "totman ti invia regolarmente un’email chiedendoti di confermare che ci sei ancora."
      },
      {
        "title": "Confermi e la routine continua",
        "body": "Finché confermi in tempo, il ciclo si azzera e la consegna preparata resta intatta."
      },
      {
        "title": "Un’assenza significativa cambia lo stato",
        "body": "Se le conferme mancano abbastanza a lungo, totman lo tratta come una reale assenza e non come un singolo momento mancato."
      },
      {
        "title": "La consegna preparata parte",
        "body": "I messaggi preparati vengono inviati individualmente, e il follow-up aggiuntivo può terminare dopo la conferma di ricezione."
      }
    ],
    "summary": [
      {
        "title": "Silenzioso nella vita normale",
        "body": "Quando la tua routine funziona, totman resta in background invece di richiedere attenzione continua."
      },
      {
        "title": "Preparato prima del bisogno",
        "body": "Tempistiche, destinatari, messaggi individuali e file opzionali vengono decisi in anticipo, prima dell’urgenza."
      },
      {
        "title": "I problemi non restano nascosti",
        "body": "Se totman rileva un problema di configurazione o runtime, può inviare un avviso separato al tuo indirizzo."
      },
      {
        "title": "Link protetti, traccia chiara",
        "body": "Log dettagliati aiutano a rivedere cosa è successo; link di conferma, ACK e download sono protetti da firma HMAC."
      },
      {
        "title": "Personale, non broadcast",
        "body": "I messaggi di escalation partono uno per uno, rendendo la consegna più chiara e privata di un invio comune."
      },
      {
        "title": "Chiusura netta del follow-up",
        "body": "Quando la ricezione di un evento è confermata, il follow-up aggiuntivo può terminare in modo pulito."
      }
    ],
    "roadmap": [
      {
        "title": "Consegna intermedia di sicurezza",
        "body": "Un passaggio intermedio opzionale potrebbe permettere a persone fidate di intervenire prima dell’escalation finale."
      },
      {
        "title": "Opzioni di hosting flessibili",
        "body": "Lavori futuri potrebbero valutare se una modalità di hosting condiviso più limitata sia praticabile."
      },
      {
        "title": "Migliorie alla gestione mail",
        "body": "Le idee previste includono controlli di priorità più ricchi e opzioni di formato di output."
      },
      {
        "title": "Comodità per l’operatore",
        "body": "Un futuro livello di gestione via browser potrebbe rendere più semplice mantenere destinatari e messaggi."
      },
      {
        "title": "Privacy destinatario più forte",
        "body": "Lavori successivi potrebbero coprire messaggi finali cifrati e modelli di consegna split-secret."
      },
      {
        "title": "Resilienza e portabilità",
        "body": "Idee a lungo termine includono implementazioni alternative, failover e cambiamenti di storage."
      }
    ],
    "footer": {
      "github": "GitHub",
      "copyright": "MacSteini © 2026",
      "docs": "Documentazione",
      "aria": "Piè di pagina"
    },
    "language": {
      "aria": "Lingua",
      "current": "Lingua attuale"
    },
    "brandAria": "Homepage di totman",
    "brandTag": "Un interruttore uomo morto per email.",
    "navAria": "Principale",
    "eyebrow": "Un interruttore uomo morto completamente autogestito per email",
    "h1": "Il tuo messaggio, consegnato quando conta di più.",
    "lead": "totman trasforma un piano privato di consegna in una routine precisa e autogestita. Effettua check-in secondo il tuo calendario, rispetta le finestre di sicurezza configurate e invia messaggi e file specifici per destinatario solo quando le conferme mancano abbastanza a lungo.",
    "showcaseAria": "Anteprima di totman",
    "showcaseAlt": "Anteprima sovrapposta delle pagine messaggio di totman.",
    "skip": "Vai al contenuto",
    "stepsKicker": "Flusso",
    "stepsTitle": "Cosa succede in pratica.",
    "summaryKicker": "Sintesi",
    "summaryTitle": "Cosa fa davvero totman per te.",
    "roadmapKicker": "Roadmap",
    "roadmapTitle": "Dove potrebbe andare totman."
  }
};

(function () {
var supportedLocales = ['en-US', 'en-GB', 'de-DE', 'es-ES', 'fr-FR', 'it-IT'];
var primaryLocaleByLanguage = {en: 'en-US', de: 'de-DE', es: 'es-ES', fr: 'fr-FR', it: 'it-IT'};
var defaultLocale = 'en-US';
var storageKey = 'totmann-site-locale';
var languageMeta = {
'en-US': {flag: '🇺🇸', name: 'English (US)'},
'en-GB': {flag: '🇬🇧', name: 'English (UK)'},
'de-DE': {flag: '🇩🇪', name: 'Deutsch'},
'es-ES': {flag: '🇪🇸', name: 'Español'},
'fr-FR': {flag: '🇫🇷', name: 'Français'},
'it-IT': {flag: '🇮🇹', name: 'Italiano'}
};

function normaliseLocale(locale) {
if (!locale || typeof locale !== 'string') { return ''; }
var parts = locale.replace('_', '-').split('-');
if (!parts[0]) { return ''; }
var language = parts[0].toLowerCase();
if (!parts[1]) { return language; }
return language + '-' + parts[1].toUpperCase();
}

function isSupported(locale) { return supportedLocales.indexOf(locale) !== -1; }

function storedLocale() {
try {
var locale = normaliseLocale(window.localStorage.getItem(storageKey));
return isSupported(locale) ? locale : '';
} catch (error) { return ''; }
}

function rememberLocale(locale) {
if (!isSupported(locale)) { return; }
try { window.localStorage.setItem(storageKey, locale); } catch (error) {}
}

function resolveLocale(preferredLocales) {
for (var i = 0; i < preferredLocales.length; i += 1) {
var locale = normaliseLocale(preferredLocales[i]);
if (isSupported(locale)) { return locale; }
}
for (var j = 0; j < preferredLocales.length; j += 1) {
var language = normaliseLocale(preferredLocales[j]).split('-')[0];
if (primaryLocaleByLanguage[language]) { return primaryLocaleByLanguage[language]; }
}
return defaultLocale;
}

function pathValue(source, path) {
return path.split('.').reduce(function (current, key) { return current && current[key]; }, source);
}

function setText(selector, text) {
var element = document.querySelector(selector);
if (element) { element.textContent = text; }
}

function applyTextBindings(localeData) {
document.querySelectorAll('[data-i18n]').forEach(function (element) {
var value = pathValue(localeData, element.getAttribute('data-i18n'));
if (typeof value === 'string') { element.textContent = value; }
});
document.querySelectorAll('[data-i18n-attr]').forEach(function (element) {
element.getAttribute('data-i18n-attr').split(';').forEach(function (binding) {
var parts = binding.split(':');
var value = pathValue(localeData, parts[1]);
if (parts[0] && typeof value === 'string') { element.setAttribute(parts[0], value); }
});
});
}

function renderNav(container, items) {
container.innerHTML = items.map(function (item) { return '<a href="' + item.href + '">' + item.label + '</a>'; }).join('');
}

function renderButtons(container, items) {
container.innerHTML = items.map(function (item) { return '<a class="' + item.class + '" href="' + item.href + '">' + item.label + '</a>'; }).join('');
}

function cardHtml(item, className, tag, href) {
var open = href ? '<a class="' + className + '" href="' + href + '">' : '<' + tag + ' class="' + className + '">';
var close = href ? '</a>' : '</' + tag + '>';
return open + '<h3>' + item.title + '</h3><p>' + item.body + '</p>' + close;
}

function renderSection(section) {
var element = document.querySelector('[data-section="' + section.id + '"]');
var grid = section.id === 'use-cases' ? 'scenario-grid' : 'card-grid';
var cardClass = section.id === 'use-cases' ? 'scenario' : 'card';
if (!element) { return; }
element.innerHTML = '<div class="section-head"><p class="section-kicker">' + section.kicker + '</p><h2 id="' + section.id + '-title">' + section.title + '</h2></div><div class="' + grid + '">' + section.cards.map(function (item) { return cardHtml(item, cardClass, 'article'); }).join('') + '</div>';
}

function renderSteps(localeData) {
var element = document.querySelector('[data-steps]');
if (!element) { return; }
element.innerHTML = '<div class="section-head"><p class="section-kicker">' + localeData.stepsKicker + '</p><h2 id="how-it-works-title">' + localeData.stepsTitle + '</h2></div><ol class="steps">' + localeData.steps.map(function (item) { return '<li><h3>' + item.title + '</h3><p>' + item.body + '</p></li>'; }).join('') + '</ol>';
}

function renderSummary(localeData) {
var element = document.querySelector('[data-summary]');
if (!element) { return; }
element.innerHTML = '<div class="section-head"><p class="section-kicker">' + localeData.summaryKicker + '</p><h2 id="summary-title">' + localeData.summaryTitle + '</h2></div><div class="summary-grid">' + localeData.summary.map(function (item) { return cardHtml(item, 'summary-card', 'article'); }).join('') + '</div>';
}

function renderRoadmap(localeData) {
var element = document.querySelector('[data-roadmap]');
if (!element) { return; }
element.innerHTML = '<div class="section-head"><p class="section-kicker">' + localeData.roadmapKicker + '</p><h2 id="roadmap-title">' + localeData.roadmapTitle + '</h2></div><div class="roadmap-grid">' + localeData.roadmap.map(function (item) { return cardHtml(item, 'roadmap-card', 'a', 'https://github.com/MacSteini/totmannschalter/blob/main/docs/Roadmap.md'); }).join('') + '</div>';
}

function renderLanguageSwitch(locale, localeData) {
var container = document.querySelector('[data-language-switch]');
if (!container) { return; }
container.innerHTML = supportedLocales.map(function (item) {
var meta = languageMeta[item];
var current = item === locale;
var label = meta.name + (current ? ' (' + localeData.language.current + ')' : '');
return '<a href="#" hreflang="' + item + '" lang="' + item + '" title="' + meta.name + '" data-locale="' + item + '" aria-label="' + label + '"' + (current ? ' aria-current="page"' : '') + '><span aria-hidden="true">' + meta.flag + '</span></a>';
}).join('');
}

function applyLocale(locale) {
var localeData = TOTMANN_LOCALES[locale] || TOTMANN_LOCALES[defaultLocale];
document.documentElement.lang = locale;
document.title = localeData.meta.title;
['description', 'ogTitle', 'ogDescription', 'twitterTitle', 'twitterDescription'].forEach(function (key) {
var selector = key === 'description' ? 'meta[name="description"]' : key.indexOf('twitter') === 0 ? 'meta[name="' + key.replace('twitter', 'twitter:').toLowerCase() + '"]' : 'meta[property="og:' + key.replace('og', '').toLowerCase() + '"]';
var element = document.querySelector(selector);
if (element) { element.setAttribute('content', localeData.meta[key]); }
});
applyTextBindings(localeData);
renderNav(document.querySelector('[data-i18n-list="nav"]'), localeData.nav);
renderButtons(document.querySelector('[data-i18n-list="buttons"]'), localeData.buttons);
localeData.sections.forEach(renderSection);
renderSteps(localeData);
renderSummary(localeData);
renderRoadmap(localeData);
renderLanguageSwitch(locale, localeData);
}

document.addEventListener('DOMContentLoaded', function () {
var root = document.documentElement;
var header = document.querySelector('.site-header');
function syncHeaderOffset() {
if (!header) { return; }
root.style.setProperty('--header-offset', header.offsetHeight + 'px');
}
var stored = storedLocale();
var preferred = stored ? [stored] : Array.prototype.slice.call(window.navigator.languages || []);
if (!preferred.length && window.navigator.language) { preferred = [window.navigator.language]; }
applyLocale(resolveLocale(preferred));
syncHeaderOffset();
window.addEventListener('resize', syncHeaderOffset);
document.addEventListener('click', function (event) {
if (!event.target.closest) { return; }
var link = event.target.closest('[data-locale]');
if (!link) { return; }
event.preventDefault();
var locale = link.getAttribute('data-locale');
rememberLocale(locale);
applyLocale(locale);
syncHeaderOffset();
});
});
}());
