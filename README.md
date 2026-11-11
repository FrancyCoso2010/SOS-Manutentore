<p align="center">
  <img src="public/assets/logo.svg" alt="SOS Manutentore" width="360">
</p>

# SOS Manutentore

Pannello web per la manutenzione interna di un'azienda: gli operatori segnalano i guasti, i
manutentori lavorano da una coda condivisa, le attività ricorrenti si ripresentano da sole.
Tutto tracciato, senza fogli Excel e senza telefonate a vuoto.

Ogni azienda installa il pannello sul proprio server: **una installazione = una azienda**. I dati
restano nella tua infrastruttura.

---

## Privacy: nessun dato condiviso, tutto nel tuo server

SOS Manutentore non è un servizio cloud e **non condivide nessuna informazione con nessun altro**:

- nessun dato viene inviato a server esterni, a terze parti, a fornitori o a servizi di analytics;
- nessuna telemetria, nessun tracciamento, nessuna pubblicità, nessun account remoto;
- nessuna CDN e nessun font remoto: l'interfaccia usa solo file locali e i font di sistema;
- il database (SQLite in `storage/` oppure MySQL) vive sul server dell'azienda e non viene mai replicato altrove;
- segnalazioni, utenti, attività, registro, export CSV e backup restano nella tua infrastruttura;
- ogni installazione è indipendente: una per azienda, senza dati condivisi tra aziende diverse;
- anche gli operatori vedono solo le proprie segnalazioni; lo staff vede solo i dati della propria azienda.

L'unico traffico di rete è quello tra il browser dei dipendenti e il server aziendale. Proteggi
l'accesso con la rete interna (LAN o VPN) e, se il pannello è raggiungibile da fuori, con HTTPS.

---

## Funzioni principali

- **Segnalazione in tre tocchi** — cosa non funziona, macchina, categoria e urgenza.
- **Coda condivisa** — priorità calcolata da urgenza e ritardi; si vede subito chi ha preso in carico.
- **Manutenzione programmata** — frequenze in giorni, prossima scadenza automatica, storico esecuzioni.
- **Dashboard per ruolo** — l'operatore vede le sue segnalazioni, lo staff vede il lavoro della giornata.
- **Turno di oggi** — segnalazioni arrivate oggi e attività da eseguire in una sola vista, stampabile.
- **Statistiche** — volumi per categoria, urgenza, andamento mensile e tempi di chiusura.
- **Registro attività** — ogni azione con autore, data e dettaglio (solo amministratore).
- **Esportazione CSV** — l'elenco filtrato delle segnalazioni, pronto per Excel.
- **Scheda stampabile** — la singola segnalazione si stampa pulita, senza menu.
- **Temi per account** — Chiaro, Scuro e Alto contrasto, salvati sull'utente.
- **Mobile-first** — sidebar a scomparsa, aree di tocco grandi, leggibile in officina.

## Requisiti

- PHP **8.0+** con estensione **PDO**
- SQLite: `pdo_sqlite` (di solito già presente)
- MySQL/MariaDB: `pdo_mysql`
- Nessun Composer, nessun framework, nessun build step

## Avvio rapido

```bash
php -S localhost:8000 -t public
```

Apri <http://localhost:8000> e accedi con **admin / admin**.

Al primo accesso il pannello **obbliga** a:

1. cambiare la password di default;
2. inserire i dati dell'azienda: nome, luogo, orario di lavoro e fuso orario.

Data e ora vengono rilevate dalla macchina che ospita il pannello; il fuso orario è modificabile.
Finché la configurazione non è completa, nessun'altra pagina è raggiungibile.

Il database viene creato automaticamente in `storage/manutenzioni.sqlite` al primo avvio.

## Ruoli

| Ruolo | Cosa può fare |
|-------|----------------|
| **Amministratore** | Tutto: utenti, categorie, macchine, segnalazioni, attività, statistiche, registro |
| **Manutentore** | Coda condivisa, presa in carico e chiusura, attività ricorrenti, categorie, statistiche |
| **Operatore** | Invia segnalazioni e vede solo le proprie |

Non esiste auto-registrazione: gli account li crea solo l'amministratore da **Utenti**. Ogni nuovo
utente riceve una password temporanea e al primo accesso deve cambiarla.

## Flusso di lavoro

1. L'operatore apre **Segnala guasto**: titolo, descrizione, macchina, categoria e urgenza.
2. La segnalazione entra nella **coda condivisa**, ordinata per priorità.
3. Un manutentore la **prende in carico**: gli altri vedono il suo nome.
4. A intervento finito la segna **risolta**, con una nota facoltativa.
5. Resta nel database con stato `risolto`, consultabile con filtri, statistiche ed export.

### Priorità

Due scale separate:

- **Categoria** dell'impianto (gestibile da admin e manutentori): Utensile, Macchinario,
  Linea di produzione, Utilità…
- **Urgenza** fissa, scelta da chi segnala: Fermo impianto, Alta, Media, Bassa

La coda unisce segnalazioni aperte e attività scadute. Ordine: fermo impianto → alta → media →
attività in ritardo → bassa. A parità di urgenza, le segnalazioni non ancora prese in carico
salgono sopra quelle già assegnate.

### Manutenzione programmata

Ogni attività ha una frequenza in giorni (7 = settimanale, 14 = ogni due settimane, 30 = mensile).
Quando viene segnata come fatta, la prossima scadenza si sposta avanti della frequenza.
Se la scadenza passa senza esecuzione, l'attività resta in ritardo e sale in cima alla coda.
Ogni completamento è registrato in `task_logs` con autore, data e nota.

## Interfaccia

- **Sidebar** con sezioni Lavoro / Gestione / Account, icone e badge sulle voci con lavoro arretrato.
- Sotto i 1024 px diventa un **drawer** con hamburger, sfondo oscurato, chiusura con Esc o tocco fuori.
- **Tema** scelto da **Profilo → Tema dell'interfaccia**, con anteprima immediata:
  - `chiaro` (default)
  - `scuro`
  - `contrasto` — bordi neri spessi e giallo/nero, pensato per officine molto illuminate
- Il tema è una colonna di `users`: resta dopo il logout e vale solo per quell'account.
- L'elenco segnalazioni si esporta in **CSV** (separatore `;`, BOM per Excel italiano) mantenendo i filtri.
- La scheda di una segnalazione ha il pulsante **Stampa** con impaginazione dedicata.
- Rispetta `prefers-reduced-motion`; link "Vai al contenuto" e focus visibile per la tastiera.

## Struttura

```
config/config.php               configurazione applicazione e database
src/bootstrap.php               bootstrap (config, helper, DB, impostazioni, sessione, layout)
src/Database.php                connessione PDO, schema, utente admin e categorie iniziali
src/Auth.php                    login, logout, ruoli, cambio password, guardie di accesso
src/Settings.php                impostazioni azienda chiave/valore
src/Activity.php                registro attività (chi ha fatto cosa)
src/helpers.php                 escape, CSRF, flash, temi, date, etichette, asset con cache busting
src/layout.php                  sidebar, topbar, icone, logo, messaggi
public/index.php                smista verso login o dashboard
public/login.php                accesso
public/logout.php               uscita (solo POST con token CSRF)
public/setup.php                configurazione forzata al primo accesso
public/dashboard.php            dashboard per ruolo
public/turno.php                turno di oggi: segnalazioni e attività della giornata
public/segnalazioni.php         elenco con filtri ed export CSV (staff)
public/segnalazione.php         dettaglio, presa in carico, chiusura, storico, stampa
public/nuova-segnalazione.php   form di segnalazione
public/mie-segnalazioni.php     segnalazioni dell'operatore
public/attivita.php             manutenzione programmata
public/categorie.php            categorie e macchine
public/utenti.php               gestione utenti (admin)
public/statistiche.php          statistiche e andamento
public/log.php                  registro attività (admin)
public/profilo.php              dati account, tema, cambio password
public/assets/app.css           stile unico, temi e regole di stampa
public/assets/logo.svg          logo orizzontale
public/assets/logo-mark.svg     marchio quadrato
public/assets/favicon.svg       favicon
storage/                        database (non versionato)
```

`public/` è l'unica cartella da esporre: `config/`, `src/` e il database restano fuori dalla
document root.

## Tabelle

| Tabella | Contenuto |
|---------|-----------|
| `users` | account, ruolo, stato, tema, password da cambiare, ultimo accesso |
| `settings` | dati azienda, orari, fuso orario, stato del setup |
| `categories` | categorie degli impianti |
| `assets` | macchine e impianti, collegati a una categoria |
| `reports` | segnalazioni: stato aperto/risolto, urgenza, presa in carico, chiusura |
| `tasks` | attività ricorrenti con frequenza e prossima scadenza |
| `task_logs` | completamenti delle attività |
| `activity_log` | registro di tutte le azioni |

Lo schema viene creato e aggiornato a ogni avvio con `CREATE TABLE IF NOT EXISTS` e `ALTER TABLE`
mirati: un database creato da una versione precedente si aggiorna da solo (colonne
`must_change_password` e `theme` aggiunte se mancanti).

## Passare a MySQL

In `config/config.php` imposta `driver` su `mysql` e compila host, database, username e password.
Crea prima il database:

```sql
CREATE DATABASE manutenzioni CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

Le tabelle vengono create automaticamente al primo accesso.

## Sicurezza

- Password con `password_hash()` / `password_verify()`, mai in chiaro
- Query preparate PDO su tutte le interrogazioni
- Token CSRF su tutti i form e sulle azioni POST
- Rigenerazione dell'ID di sessione al login
- Cookie `HttpOnly` + `SameSite=Lax`, `Secure` in HTTPS
- Escape HTML di tutti gli output
- Controllo dei ruoli lato server su ogni pagina e ogni azione
- Messaggio d'errore generico e tempo di risposta uniforme per utenti inesistenti
- Cambio password obbligatorio al primo accesso e dopo un reset dell'amministratore
- Impossibile disattivare l'ultimo amministratore attivo

## Backup e ripristino

Con SQLite fermare il pannello e copiare `storage/manutenzioni.sqlite`: contiene tutto (utenti,
segnalazioni, attività, registro). Con MySQL usare `mysqldump`.

Per ripristinare: rimettere il file al suo posto (o ricaricare il dump) e riavviare il pannello.

## Problemi comuni

| Sintomo | Soluzione |
|---------|-----------|
| `database is locked` | chiudere altre istanze del pannello o processi che tengono aperto il file SQLite |
| Errore di scrittura in `storage/` | dare permesso di scrittura alla cartella al processo PHP |
| Porta 8000 occupata | `php -S localhost:8080 -t public` |
| Password dimenticata | da terminale: `php -r '$p=new PDO("sqlite:storage/manutenzioni.sqlite"); echo password_hash("NuovaPass123", PASSWORD_DEFAULT);'` e aggiornare `users.password_hash`, poi impostare `must_change_password = 1` |
| Ripartire da zero | cancellare `storage/manutenzioni.sqlite` e ricaricare la pagina di login |

## Sviluppo

Non ci sono dipendenze da installare. Per controllare la sintassi:

```bash
find . -name '*.php' -not -path './storage/*' -exec php -l {} \;
```

Il foglio di stile è unico (`public/assets/app.css`); i temi sono variabili CSS su
`:root`, `[data-theme="scuro"]` e `[data-theme="contrasto"]`. Il link al CSS usa un parametro
di versione basato sulla data di modifica, quindi le modifiche allo stile si vedono subito.

---

Uso interno. Marchio e interfaccia: **SOS Manutentore**.
