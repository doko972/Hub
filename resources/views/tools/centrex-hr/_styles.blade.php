<style>
  .chr-app {
    --ink: #17283b; --muted: #586d7c; --line: #dce7eb; --accent: #007f87;
    --surface: #fff; --canvas: #f2f7f8;
    margin: 0; min-height: 60vh; color: var(--ink);
    background: radial-gradient(circle at 90% 0%, #d9f0ef 0, transparent 35rem), var(--canvas);
    font-family: Inter, ui-sans-serif, system-ui, -apple-system, "Segoe UI", sans-serif;
    font-size: 15px;
  }
  .chr-app *, .chr-app *::before, .chr-app *::after { box-sizing: border-box; }
  .chr-app [hidden] { display: none !important; }
  .chr-app a { color: #006b79; }
  /* Le thème du menu global est limité aux deux pages principales Centrex-HR. */
  body:has(.chr-sidebar-theme) #sidebar {
    background: linear-gradient(160deg, #124464 0%, #0b6575 62%, #0d8b84 100%);
    color: #e2f4f4;
    border-right-color: #c5fff044;
  }
  body:has(.chr-sidebar-theme) #sidebar .sidebar__profile,
  body:has(.chr-sidebar-theme) #sidebar .sidebar__nav,
  body:has(.chr-sidebar-theme) #sidebar .sidebar__section { background: transparent; }
  body:has(.chr-sidebar-theme) #sidebar .sidebar-avatar__name,
  body:has(.chr-sidebar-theme) #sidebar .sidebar-footer__name { color: #fff; }
  body:has(.chr-sidebar-theme) #sidebar .sidebar__section-title,
  body:has(.chr-sidebar-theme) #sidebar .sidebar-footer__version { color: #a7e8dd; }
  body:has(.chr-sidebar-theme) #sidebar a,
  body:has(.chr-sidebar-theme) #sidebar .sidebar__section-toggle { color: #e2f4f4; }
  body:has(.chr-sidebar-theme) #sidebar .sidebar__section-toggle { background: transparent; }
  body:has(.chr-sidebar-theme) #sidebar .sidebar__link:hover,
  body:has(.chr-sidebar-theme) #sidebar .sidebar__link:focus-visible,
  body:has(.chr-sidebar-theme) #sidebar .sidebar__section-toggle:hover { background: #ffffff23; color: #fff; }
  body:has(.chr-sidebar-theme) #sidebar .sidebar__link.is-active {
    background: #d7fff1; color: #0a4d58; box-shadow: 0 4px 14px #07364a24;
  }
  body:has(.chr-sidebar-theme) #sidebar .sidebar__link svg,
  body:has(.chr-sidebar-theme) #sidebar .sidebar__section-chevron { color: inherit; stroke: currentColor; }
  body:has(.chr-sidebar-theme) #sidebar .sidebar__divider { border-color: #d9f6f638; background-color: #d9f6f638; }
  body:has(.chr-sidebar-theme) #sidebar .sidebar__footer { background: #042f3b24; border-top-color: #d9f6f638; }
  body:has(.chr-sidebar-theme) #sidebar .sidebar-footer__author,
  body:has(.chr-sidebar-theme) #sidebar .sidebar-avatar__link,
  body:has(.chr-sidebar-theme) #sidebar .sidebar-footer__collab { color: #d6efed; }
  body:has(.chr-sidebar-theme) #sidebar a:focus-visible,
  body:has(.chr-sidebar-theme) #sidebar button:focus-visible { outline: 3px solid #f3c36a; outline-offset: 2px; }
  .chr-app .ops-shell { max-width: 1480px; margin: 0 auto; padding: 0 clamp(16px, 3vw, 42px) 45px; }
  .chr-app .ops-topbar { min-height: 86px; display: flex; justify-content: space-between; align-items: center; gap: 16px; }
  .chr-app .ops-brand { display: flex; align-items: center; gap: 11px; text-decoration: none; color: #173049; font-size: 1.15rem; font-weight: 800; letter-spacing: -.02em; line-height: 1.1; }
  .chr-app .ops-brand small { display: block; margin-top: 4px; color: var(--muted); font-size: .68rem; font-weight: 650; letter-spacing: .07em; text-transform: uppercase; }
  .chr-app .ops-brand-mark { display: grid; place-items: center; flex: 0 0 42px; width: 42px; height: 42px; border-radius: 12px; background: #07364a; color: #75e0dc; font-size: .88rem; letter-spacing: .02em; box-shadow: 0 6px 16px #07364a24; }
  .chr-app .ops-nav { display: flex; align-items: center; flex-wrap: wrap; gap: 6px; }
  .chr-app .ops-nav a { border-radius: 9px; padding: 10px 13px; text-decoration: none; color: #506579; font-size: .87rem; font-weight: 650; }
  .chr-app .ops-nav a:hover, .chr-app .ops-nav a:focus-visible { background: #e2eef1; color: #075d68; }
  .chr-app .ops-nav a.active { background: #dbecef; color: #075e68; }
  .chr-app .ops-hero { position: relative; overflow: hidden; display: flex; justify-content: space-between; align-items: end; gap: 30px; padding: clamp(27px, 4vw, 48px); border-radius: 21px; color: #fff; background: linear-gradient(115deg, #124464 0%, #0b6575 62%, #0d8b84 100%); box-shadow: 0 18px 38px #10394c26; }
  .chr-app .ops-hero-fleet { min-height: 198px; }
  .chr-app .ops-hero::before { content: ""; position: absolute; width: 320px; height: 320px; right: -90px; top: -190px; border: 1px solid #ffffff25; border-radius: 50%; box-shadow: 0 0 0 55px #ffffff08, 0 0 0 110px #ffffff08; pointer-events: none; }
  .chr-app .ops-hero-copy, .chr-app .ops-hero-aside { position: relative; z-index: 1; }
  .chr-app .ops-kicker { color: #92e7df; font-size: .75rem; font-weight: 800; letter-spacing: .08em; text-transform: uppercase; }
  .chr-app .ops-kicker a { color: #92e7df; text-decoration: none; }
  .chr-app .ops-kicker a:hover { text-decoration: underline; }
  .chr-app .ops-pulse { display: inline-block; width: 8px; height: 8px; margin-right: 7px; border-radius: 50%; background: #7cdeb8; box-shadow: 0 0 0 4px #7cdeb823; }
  .chr-app .ops-hero h1 { margin: 14px 0 11px; color: #ffffff; font-size: clamp(2.15rem, 3.3vw, 3.25rem); font-weight: 800; letter-spacing: -.045em; line-height: 1.08; text-shadow: 0 2px 13px #06354742; }
  .chr-app .ops-hero h1 span { color: #c5fff0; font-weight: 560; }
  .chr-app .ops-hero p { max-width: 750px; margin: 0; color: #e2f4f4; line-height: 1.6; }
  .chr-app .ops-hero-aside { flex: 0 0 min(29%, 290px); padding: 15px 18px; border: 1px solid #ffffff2b; border-radius: 12px; background: #ffffff14; font-size: .81rem; line-height: 1.5; }
  .chr-app .ops-hero-aside strong, .chr-app .ops-hero-aside span { display: block; }
  .chr-app .ops-hero-aside strong { margin-bottom: 7px; color: #fff; font-size: .9rem; }
  .chr-app .ops-hero-aside span { color: #d4e9eb; }
  .chr-app .ops-grid { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 15px; margin: 22px 0; }
  .chr-app .ops-card, .chr-app .ops-panel { border: 1px solid var(--line); border-radius: 17px; background: var(--surface); box-shadow: 0 6px 24px #152c3d0a; }
  .chr-app .ops-card { position: relative; overflow: hidden; min-height: 137px; padding: 21px 21px 18px; }
  .chr-app .ops-card::before { position: absolute; content: ""; left: 0; top: 0; width: 100%; height: 3px; background: #abc5cf; }
  .chr-app .ops-card.good::before { background: #20a27f; }
  .chr-app .ops-card.danger::before { background: #de776b; }
  .chr-app .ops-card .label { color: #506678; font-size: .8rem; font-weight: 750; }
  .chr-app .ops-card .number { margin: 9px 0 3px; font-size: clamp(1.75rem, 2.7vw, 2.3rem); line-height: 1.1; font-weight: 850; font-variant-numeric: tabular-nums; }
  .chr-app .ops-card.good .number { color: #097862; }
  .chr-app .ops-card.danger .number { color: #b13c3d; }
  .chr-app .ops-card .detail { color: var(--muted); font-size: .76rem; line-height: 1.5; }
  .chr-app .ops-panel { margin: 19px 0; padding: clamp(18px, 2.5vw, 29px); }
  .chr-app .ops-panel h2 { margin: 0 0 14px; color: #18344b; font-size: 1.22rem; letter-spacing: -.015em; }
  .chr-app .ops-panel p { line-height: 1.56; }
  .chr-app .ops-fleet-panel { margin-top: 22px; }
  .chr-app .ops-panel-heading { display: flex; justify-content: space-between; align-items: center; gap: 16px; margin-bottom: 17px; }
  .chr-app .ops-panel-heading h2 { margin: 5px 0 4px; font-size: clamp(1.38rem, 2vw, 1.72rem); letter-spacing: -.035em; }
  .chr-app .ops-panel-heading p { margin: 0; color: var(--muted); font-size: .84rem; }
  .chr-app .ops-eyebrow { color: #087c82; font-size: .68rem; letter-spacing: .12em; font-weight: 850; }
  .chr-app .ops-total { display: inline-flex; align-items: baseline; gap: 6px; border-radius: 12px; padding: 9px 14px; background: #e7f5f3; color: #075c67; font-size: 1.25rem; font-weight: 850; white-space: nowrap; font-variant-numeric: tabular-nums; }
  .chr-app .ops-total small { font-size: .74rem; font-weight: 650; }
  .chr-app .ops-note { border-left: 3px solid #428eab; border-radius: 7px; padding: 11px 14px; background: #eef7f9; color: #34566c; font-size: .86rem; }
  .chr-app .ops-warn { background: #fff7e8; color: #795626; border-left-color: #d49b45; }
  .chr-app .ops-action { display: inline-flex; align-items: center; gap: 12px; border-radius: 9px; padding: 10px 14px; background: #e5f3f3; color: #08686e; text-decoration: none; font-size: .86rem; font-weight: 800; }
  .chr-app .ops-action:hover { background: #cee9e8; }
  .chr-app .ops-toolbar { display: flex; flex-wrap: wrap; align-items: end; gap: 11px; margin: 17px 0 20px; }
  .chr-app .ops-toolbar label { display: grid; gap: 6px; color: #506477; font-size: .8rem; font-weight: 750; }
  .chr-app .ops-toolbar input, .chr-app .ops-toolbar select { height: 44px; max-width: 100%; border: 1px solid #b9cbd4; border-radius: 9px; padding: 9px 12px; background: #fff; color: var(--ink); font: inherit; }
  .chr-app .ops-toolbar input:focus, .chr-app .ops-toolbar select:focus { border-color: #0b8990; }
  .chr-app .ops-toolbar input { width: min(370px, 84vw); }
  .chr-app .ops-fleet-toolbar { display: flex; align-items: end; justify-content: space-between; gap: 16px; flex-wrap: wrap; margin: 14px 0 20px; padding: 16px; border: 1px solid #e0ecee; border-radius: 14px; background: #f8fbfb; }
  .chr-app .ops-fleet-filters { margin: 0; }
  .chr-app .ops-search-field { position: relative; display: grid; gap: 6px; color: #506477; font-size: .8rem; font-weight: 750; }
  .chr-app .ops-search-control { position: relative; }
  .chr-app .ops-fleet-filters .ops-search-control input { width: min(390px, 75vw); padding-right: 100px; }
  .chr-app .ops-search-control input::-webkit-search-cancel-button { display: none; }
  .chr-app .ops-search-clear { position: absolute; right: 5px; top: 5px; min-height: 34px; display: inline-flex; align-items: center; border-radius: 6px; padding: 3px 7px; background: #edf5f5; color: #075f68; text-decoration: none; font-size: .75rem; font-weight: 750; }
  .chr-app .ops-search-clear:hover { background: #d7ebeb; }
  .chr-app .ops-suggestions { position: absolute; top: calc(100% + 5px); left: 0; z-index: 10; width: 100%; max-height: 280px; overflow: auto; border: 1px solid #b9d6d9; border-radius: 10px; padding: 5px; background: #fff; box-shadow: 0 14px 30px #10394c26; }
  .chr-app .ops-suggestion { display: block; width: 100%; border: 0; border-radius: 6px; padding: 9px 10px; background: transparent; color: #183e50; text-align: left; font: inherit; font-size: .81rem; font-weight: 650; cursor: pointer; overflow-wrap: anywhere; }
  .chr-app .ops-suggestion:hover, .chr-app .ops-suggestion.is-active { background: #e5f4f2; color: #075f68; }
  .chr-app .ops-fleet-filters select[name="tri"] { min-width: 218px; }
  .chr-app .ops-fleet-filters select[name="taille"] { min-width: 96px; }
  .chr-app .ops-sync { display: flex; align-items: center; gap: 12px; color: var(--muted); font-size: .72rem; white-space: nowrap; }
  .chr-app .ops-sync span { display: block; border-left: 1px solid #d1e1e3; padding-left: 12px; }
  .chr-app .ops-sync strong { color: #264e59; font-variant-numeric: tabular-nums; }
  .chr-app .ops-sync form { margin: 0; }
  .chr-app .ops-action.ops-refresh { min-height: 44px; border: 1px solid #a7d2cf; padding: 10px 14px; font: inherit; font-size: .83rem; font-weight: 780; white-space: nowrap; cursor: pointer; }
  .chr-app .ops-action.ops-refresh:disabled { opacity: .66; cursor: wait; }
  .chr-app .ops-date-hint { margin: 0 0 13px; color: var(--muted); font-size: .77rem; }
  .chr-app .ops-btn { min-height: 44px; border: 0; border-radius: 9px; padding: 10px 17px; background: #087d83; color: #fff; font: inherit; font-size: .85rem; font-weight: 800; cursor: pointer; }
  .chr-app .ops-btn:hover { background: #08646b; }
  .chr-app .ops-reset { align-self: center; font-size: .83rem; font-weight: 650; }
  .chr-app .ops-table { width: 100%; border-collapse: collapse; table-layout: fixed; }
  .chr-app .ops-table th { padding: 12px; background: #f3f7f8; color: #586c79; text-align: left; text-transform: uppercase; letter-spacing: .05em; font-size: .7rem; }
  .chr-app .ops-table td { padding: 14px 12px; border-bottom: 1px solid #e8eef1; vertical-align: top; overflow-wrap: anywhere; font-size: .84rem; line-height: 1.45; }
  .chr-app .ops-table tr:last-child td { border-bottom: 0; }
  .chr-app .ops-table tbody tr:hover { background: #f8fbfb; }
  .chr-app .ops-table code { display: inline-block; border-radius: 5px; padding: 2px 6px; background: #f0f5f7; color: #365469; font-size: .79rem; }
  .chr-app .ops-fleet-table { border-collapse: separate; border-spacing: 0; }
  .chr-app .ops-table-scroll { max-width: 100%; overflow-x: auto; }
  .chr-app .ops-fleet-table { min-width: 1200px; }
  .chr-app .ops-fleet-table .ops-instance-column { width: 37.5%; }
  .chr-app .ops-fleet-table .ops-ip-column { width: 34.5%; }
  .chr-app .ops-fleet-table .ops-state-column { width: 13%; }
  .chr-app .ops-fleet-table .ops-week-column { width: 15%; }
  .chr-app .ops-fleet-table td[colspan] { width: 100%; }
  .chr-app .ops-fleet-table th, .chr-app .ops-fleet-table td { padding-left: 18px; padding-right: 18px; }
  .chr-app .ops-fleet-table th + th, .chr-app .ops-fleet-table td + td { border-left: 1px solid #dce6e9; }
  .chr-app .ops-fleet-table tbody tr:nth-child(even) { background: #fbfdfd; }
  .chr-app .ops-fleet-table tbody tr:hover { background: #eff9f8; }
  .chr-app .ops-ip-content { display: grid; gap: 4px; }
  .chr-app .ops-ip-actions { display: grid; grid-template-columns: 140px 90px 105px; align-items: center; gap: 8px; }
  .chr-app .ops-ip-link code { color: #006b79; text-decoration: underline; text-underline-offset: 2px; }
  .chr-app .ops-ip-link:hover code { background: #deeff1; }
  .chr-app .ops-copy { width: 100%; min-height: 34px; border: 1px solid #b7d4d9; border-radius: 7px; padding: 5px 8px; background: #f2f9f9; color: #075e68; font: inherit; font-size: .72rem; font-weight: 750; white-space: nowrap; cursor: pointer; }
  .chr-app .ops-copy:hover { background: #dcefee; }
  .chr-app .ops-copy-status { color: #245c65; font-size: .72rem; }
  .chr-app .ops-copy-status:empty { display: none; }
  .chr-app .ops-manual-copy { width: min(100%, 390px); border: 1px solid #b7d4d9; border-radius: 7px; padding: 7px 9px; background: #fff; color: #183e50; font: inherit; font-size: .8rem; }
  .chr-app .ops-main { display: block; max-width: 100%; color: #213a51; font-weight: 800; line-height: 1.5; overflow-wrap: anywhere; }
  .chr-app .ops-instance-link { text-decoration: underline; text-decoration-color: #97cbc9; text-underline-offset: 3px; }
  .chr-app .ops-instance-link:hover { color: #006b79; text-decoration-color: currentColor; }
  .chr-app .ops-instance-panel { padding: 22px; margin-top: 8px; }
  .chr-app .ops-instance-history { min-width: 700px; }
  .chr-app .ops-nav .ops-back-button { background: #dbecef; color: #075e68; font-weight: 800; }
  .chr-app .ops-nav .ops-back-button:hover { background: #c5e7e7; }
  .chr-app .ops-ping-periods { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 12px; margin: 16px 0 18px; }
  .chr-app .ops-ping-period { display: grid; gap: 5px; border: 1px solid #dce7eb; border-radius: 10px; padding: 13px; background: #f7fbfc; }
  .chr-app .ops-ping-period strong { color: #315469; font-size: .8rem; }
  .chr-app .ops-ping-period span { color: #0a5863; font-size: 1.1rem; font-weight: 800; }
  .chr-app .ops-ping-period small { color: var(--muted); line-height: 1.4; }
  .chr-app .ops-ping-timeline-title { margin: 18px 0 9px; color: #24495b; font-size: .9rem; }
  .chr-app .ops-ping-timeline-title small { margin-left: 8px; color: var(--muted); font-size: .74rem; font-weight: 500; }
  .chr-app .ops-ping-scroll { max-width: 100%; overflow-x: auto; }
  .chr-app .ops-ping-bars { display: grid; grid-template-columns: repeat(60, minmax(6px, 1fr)); gap: 3px; min-width: 570px; height: 48px; }
  .chr-app .ops-ping-minute { display: block; border-radius: 3px; background: #e5ecef; }
  .chr-app .ops-ping-minute.up { background: #13896c; }
  .chr-app .ops-ping-minute.down { background: #c24646; }
  .chr-app .ops-ping-week { display: grid; grid-template-columns: repeat(7, 18px); gap: 3px; width: max-content; }
  .chr-app .ops-ping-week-day { display: grid; gap: 4px; justify-items: center; color: #536a7a; cursor: help; }
  .chr-app .ops-ping-week-day small { font-size: .59rem; line-height: 1; }
  .chr-app .ops-ping-week-square, .chr-app .ops-ping-week-legend i { display: inline-block; width: 18px; height: 18px; border-radius: 4px; background: #dfe8ec; border: 1px solid #a8b8c0; }
  .chr-app .ops-ping-week-square.ok, .chr-app .ops-ping-week-legend i.ok { background: #13896c; border-color: #08765e; }
  .chr-app .ops-ping-week-square.partial, .chr-app .ops-ping-week-legend i.partial { background: #237ca8; border-color: #1b6588; }
  .chr-app .ops-ping-week-square.watch, .chr-app .ops-ping-week-legend i.watch { background: #e5a535; border-color: #bd7f1d; }
  .chr-app .ops-ping-week-square.incident, .chr-app .ops-ping-week-legend i.incident { background: #c24646; border-color: #a93131; }
  .chr-app .ops-ping-week-legend { display: flex; gap: 9px 16px; flex-wrap: wrap; margin-top: 14px; color: var(--muted); font-size: .72rem; }
  .chr-app .ops-ping-week-legend span { display: inline-flex; align-items: center; gap: 6px; }
  .chr-app .ops-ping-week-legend i { width: 12px; height: 12px; border-radius: 3px; }
  @media (max-width: 620px) { .chr-app .ops-ping-periods { grid-template-columns: 1fr; } }
  .chr-app .ops-ovh-state { display: inline-block; border-radius: 7px; padding: 5px 8px; background: #eff3f5; color: #476273; font-size: .74rem; font-weight: 750; line-height: 1.4; }
  .chr-app .ops-ovh-state.good { background: #e2f5ec; color: #147455; }
  .chr-app .ops-ovh-state.danger { background: #fce9e7; color: #aa393b; }
  .chr-app .ops-zone { color: #28485a; font-size: .8rem; font-weight: 700; overflow-wrap: anywhere; }
  .chr-app .ops-ovh-hint { margin: 12px 0 0; color: var(--muted); font-size: .75rem; line-height: 1.45; }
  .chr-app .ops-machine-date { display: block; margin-top: 3px; color: var(--muted); font-size: .72rem; }
  .chr-app .ops-muted { color: var(--muted); font-size: .79rem; }
  .chr-app .ops-badge { display: inline-block; margin: 2px 3px 2px 0; border-radius: 6px; padding: 5px 8px; background: #edf1f3; color: #445a6a; font-size: .73rem; font-weight: 750; line-height: 1.3; }
  .chr-app .ops-badge.bad { background: #fce9e7; color: #aa393b; }
  .chr-app .ops-badge.ok { background: #e2f5ec; color: #147455; }
  .chr-app .ops-badge.attention { background: #fff1d9; color: #80590b; }
  .chr-app .ops-foot { margin: 14px 0 0; color: var(--muted); font-size: .78rem; line-height: 1.5; }
  .chr-app .ops-history { display: flex; flex-wrap: wrap; gap: 10px; }
  .chr-app .ops-history a { display: inline-block; border: 1px solid #d7e6e9; border-radius: 10px; padding: 11px 14px; background: #f4fafb; text-decoration: none; line-height: 1.5; }
  .chr-app .ops-history a:hover { border-color: #74b7b8; background: #e8f5f5; }
  .chr-app .ops-components { display: grid; grid-template-columns: repeat(auto-fit, minmax(235px, 1fr)); gap: 12px; margin-top: 16px; }
  .chr-app .ops-component { padding: 16px; border: 1px solid #dce8eb; border-radius: 11px; background: #f7fbfc; line-height: 1.5; }
  .chr-app .ops-component h3 { margin: 0 0 8px; color: #203c50; font-size: .98rem; }
  .chr-app .ops-component-count { margin-bottom: 7px; font-size: .84rem; }
  .chr-app .ops-component a { display: inline-block; margin-top: 12px; font-size: .81rem; font-weight: 700; }
  .chr-app .ops-step + .ops-step { margin-top: 7px; }
  .chr-app .ops-detail { padding-top: 8px; white-space: pre-wrap; overflow-wrap: anywhere; color: #40586a; font-size: .78rem; }
  .chr-app details summary { cursor: pointer; color: #006b79; font-weight: 700; }
  .chr-app .ops-pagination { display: flex; align-items: center; gap: 8px; margin-top: 16px; }
  .chr-app .ops-pagination a, .chr-app .ops-pagination .disabled { border: 1px solid #d0e0e5; border-radius: 8px; padding: 8px 12px; text-decoration: none; font-size: .78rem; font-weight: 700; }
  .chr-app .ops-pagination .disabled { color: #96a6af; background: #f7f9fa; }
  .chr-app .ops-page-count { margin: 0 6px; color: var(--muted); font-size: .8rem; }
  .chr-app .ops-footer { margin-top: 30px; color: #738696; text-align: center; font-size: .74rem; }
  .chr-app :focus-visible { outline: 3px solid #f3ab54; outline-offset: 2px; }
  @media (max-width: 1500px) {
    .chr-app .ops-fleet-toolbar { align-items: stretch; }
    .chr-app .ops-sync { flex-wrap: wrap; }
    .chr-app .ops-ip-actions { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    .chr-app .ops-ip-link { grid-column: 1 / -1; }
  }
  @media (max-width: 980px) {
    .chr-app .ops-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    .chr-app .ops-hero-aside { flex-basis: 35%; }
  }
  @media (max-width: 700px) {
    .chr-app .ops-shell { padding: 0 12px 35px; }
    .chr-app .ops-topbar { align-items: flex-start; flex-direction: column; padding: 15px 0 12px; gap: 12px; }
    .chr-app .ops-nav { width: 100%; }
    .chr-app .ops-nav a { padding: 7px 10px; font-size: .78rem; }
    .chr-app .ops-hero { display: block; padding: 25px 20px; border-radius: 13px; }
    .chr-app .ops-hero-aside { margin-top: 20px; }
    .chr-app .ops-panel-heading { align-items: flex-start; flex-direction: column; }
    .chr-app .ops-fleet-toolbar { padding: 12px; }
    .chr-app .ops-fleet-filters { width: 100%; }
    .chr-app .ops-fleet-filters label, .chr-app .ops-fleet-filters .ops-search-field, .chr-app .ops-fleet-filters input, .chr-app .ops-fleet-filters select { width: 100%; max-width: 100%; }
    .chr-app .ops-fleet-filters .ops-search-control input { width: 100%; }
    .chr-app .ops-fleet-filters select[name="tri"], .chr-app .ops-fleet-filters select[name="taille"] { min-width: 0; }
    .chr-app .ops-sync { align-items: flex-start; flex-direction: column; gap: 7px; white-space: normal; }
    .chr-app .ops-sync span { border-left: 0; padding-left: 0; }
    .chr-app .ops-grid { gap: 9px; margin: 14px 0; }
    .chr-app .ops-card { min-height: 128px; padding: 16px 13px; }
    .chr-app .ops-card .detail { font-size: .69rem; }
    .chr-app .ops-table, .chr-app .ops-table tbody, .chr-app .ops-table tr, .chr-app .ops-table td { display: block; width: 100%; }
    .chr-app .ops-table thead { display: none; }
    .chr-app .ops-table tr { margin: 9px 0; border: 1px solid #dce7eb; border-radius: 9px; padding: 6px; }
    .chr-app .ops-table td { display: flex; justify-content: space-between; gap: 10px; border-bottom: 1px solid #edf1f3; text-align: right; padding: 8px 5px; }
    .chr-app .ops-table td::before { content: attr(data-label); flex: 0 0 35%; text-align: left; color: #627989; font-size: .72rem; font-weight: 750; }
    .chr-app .ops-table td[colspan]::before { content: none; }
    .chr-app .ops-ip-content { min-width: 0; text-align: right; }
    .chr-app .ops-ip-actions { grid-template-columns: repeat(2, minmax(0, 1fr)); padding-left: 0; }
    .chr-app .ops-ip-link { grid-column: 1 / -1; justify-self: end; }
    .chr-app .ops-fleet-table td:first-child { width: 100%; }
    .chr-app .ops-fleet-table { min-width: 0; }
    .chr-app .ops-fleet-table colgroup { display: none; }
    .chr-app .ops-fleet-table td + td { border-left: 0; }
    .chr-app .ops-table tr:last-child td { border-bottom: 0; }
    .chr-app .ops-pagination { justify-content: space-between; flex-wrap: wrap; }
  }
  @media (max-width: 420px) {
    .chr-app .ops-ip-actions { grid-template-columns: minmax(0, 1fr); }
    .chr-app .ops-ip-link { grid-column: 1; }
  }
</style>
