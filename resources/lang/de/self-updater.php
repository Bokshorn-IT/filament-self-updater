<?php

declare(strict_types=1);

return [
    'navigation_label' => 'Update',
    'title' => 'Anwendungs-Update',

    'actions' => [
        'install' => 'Update installieren',
        'install_now' => 'Jetzt installieren',
        'check' => 'Auf Updates prüfen',
        'open' => 'Zum Update',
    ],

    'modal' => [
        'install_description' => 'Version :version wird im Hintergrund heruntergeladen und installiert. Die Anwendung ist währenddessen möglicherweise kurz nicht erreichbar.',
    ],

    'notifications' => [
        'up_to_date' => 'Die Anwendung ist auf dem neuesten Stand.',
        'available' => 'Update verfügbar: :version',
        'check_failed' => 'Die Suche nach Updates ist fehlgeschlagen.',
        'in_progress' => 'Es läuft bereits ein Update.',
        'installed' => 'Version :version installiert.',
        'failed_before' => 'Das Update ist fehlgeschlagen. Die Anwendung wurde nicht verändert.',
        'failed_after' => 'Die Dateien wurden aktualisiert, aber ein Post-Update-Befehl ist fehlgeschlagen.',
    ],

    'status' => [
        'available' => 'Update verfügbar',
        'up_to_date' => 'Auf dem neuesten Stand',
        'check_failed' => 'Suche nach Updates fehlgeschlagen',
        'latest_running' => 'Die neueste Version ist installiert.',
        'ready' => 'Eine neue Version steht zur Installation bereit.',
        'installed_version' => 'Installierte Version',
        'latest_version' => 'Neueste Version',
        'unknown' => 'unbekannt',
        'badge_current' => 'Aktuell',
        'badge_new' => 'Neu',
    ],

    'run' => [
        'heading' => 'Letztes Update',
        'version' => 'Version',
        'queued_at' => 'Gestartet',
        'finished_at' => 'Beendet',
        'stage' => 'Schritt',
        'waiting_for_worker' => 'Wartet darauf, dass ein Queue-Worker das Update übernimmt.',
        'failed_before' => 'Das Update wurde abgebrochen, bevor eine Datei verändert wurde.',
        'failed_after' => 'Die Dateien sind auf :version, aber ein Post-Update-Befehl ist fehlgeschlagen. Details im Log unten.',
        'show_log' => 'Log anzeigen (:lines Zeile)|Log anzeigen (:lines Zeilen)',
        'hide_log' => 'Log ausblenden',
    ],

    'state' => [
        'queued' => 'In der Warteschlange',
        'running' => 'Läuft',
        'succeeded' => 'Installiert',
        'failed' => 'Fehlgeschlagen',
        'abandoned' => 'Abgebrochen',
    ],

    'stage' => [
        'download' => 'Herunterladen',
        'extract' => 'Entpacken',
        'install' => 'Dateien installieren',
        'post_update' => 'Post-Update-Befehle laufen',
    ],

    'widget' => [
        'current' => 'Anwendung ist aktuell',
    ],
];
