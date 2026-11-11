{{-- Shared styles for the buying and admin pages. Included once per page with @push('styles') or inline. --}}
<style>
    .at-svc-ico { display:inline-flex; align-items:center; justify-content:center; border-radius:12px; font-weight:800; letter-spacing:-.02em; color:#fff; background:var(--c); flex-shrink:0; text-shadow:0 1px 1px rgba(0,0,0,.15); }
    .at-wallet { background:linear-gradient(135deg,#0B8457 0%,#0a6b47 60%,#084d34 100%); color:#fff; border-radius:20px; padding:22px 24px; position:relative; overflow:hidden; box-shadow:0 12px 30px -12px rgba(11,132,87,.55); }
    .at-wallet::after { content:""; position:absolute; right:-40px; top:-40px; width:180px; height:180px; border-radius:50%; background:rgba(245,166,35,.25); }
    .at-wallet .lbl { font-size:12px; opacity:.85; text-transform:uppercase; letter-spacing:.08em; }
    .at-wallet .amt { font-size:34px; font-weight:800; letter-spacing:-.02em; line-height:1.15; margin:4px 0 14px; }
    .at-wallet .btn-light { font-weight:600; border-radius:10px; }
    .at-wallet .btn-outline-light { border-radius:10px; }
    .at-cat { display:flex; flex-direction:column; align-items:center; justify-content:center; gap:10px; text-align:center; padding:18px 10px; border-radius:16px; border:1px solid var(--vz-border-color,#e9ebec); background:var(--vz-card-bg,#fff); color:inherit; transition:transform .15s, box-shadow .15s, border-color .15s; height:100%; }
    .at-cat:hover { transform:translateY(-2px); box-shadow:0 10px 24px -14px rgba(0,0,0,.35); border-color:var(--c); color:inherit; }
    .at-cat .ico { width:52px; height:52px; border-radius:16px; display:inline-flex; align-items:center; justify-content:center; font-size:26px; color:var(--c); background:color-mix(in srgb, var(--c) 13%, transparent); }
    .at-cat .nm { font-weight:600; font-size:14px; }
    .at-cat .sub { font-size:11.5px; color:var(--vz-secondary-color,#878a99); }
    .at-pick { display:flex; flex-wrap:wrap; gap:10px; }
    .at-pick input { position:absolute; opacity:0; pointer-events:none; }
    .at-pick label { display:flex; align-items:center; gap:10px; padding:10px 14px; border-radius:14px; border:1.5px solid var(--vz-border-color,#e9ebec); cursor:pointer; font-weight:600; font-size:13.5px; background:var(--vz-card-bg,#fff); transition:border-color .15s, box-shadow .15s; }
    .at-pick input:checked + label { border-color:#0B8457; box-shadow:0 0 0 3px rgba(11,132,87,.15); }
    .at-pick input:focus-visible + label { outline:2px solid #0B8457; outline-offset:2px; }
    .at-plans { display:grid; grid-template-columns:repeat(auto-fill,minmax(170px,1fr)); gap:10px; }
    .at-plan { position:relative; border:1.5px solid var(--vz-border-color,#e9ebec); border-radius:14px; padding:12px 14px; cursor:pointer; background:var(--vz-card-bg,#fff); transition:border-color .15s, box-shadow .15s; }
    .at-plan.sel { border-color:#0B8457; box-shadow:0 0 0 3px rgba(11,132,87,.15); }
    .at-plan .pn { font-weight:600; font-size:13px; line-height:1.3; }
    .at-plan .pv { font-size:11.5px; color:var(--vz-secondary-color,#878a99); }
    .at-plan .pp { font-weight:800; font-size:15px; margin-top:6px; color:#0B8457; }
    .at-plan .pp s { color:var(--vz-secondary-color,#878a99); font-weight:500; font-size:12px; margin-left:4px; }
    .at-summary { border-radius:16px; background:var(--vz-light,#f3f6f9); padding:16px 18px; }
    .at-summary .row-l { display:flex; justify-content:space-between; padding:5px 0; font-size:13.5px; }
    .at-summary .row-l.total { border-top:1px dashed var(--vz-border-color,#ced4da); margin-top:6px; padding-top:10px; font-weight:800; font-size:16px; }
    .at-verify { border-radius:12px; padding:10px 12px; font-size:13px; }
    .at-token { font-family:ui-monospace,SFMono-Regular,Menlo,monospace; font-size:18px; letter-spacing:.06em; background:#fff8e6; border:1px dashed #F5A623; border-radius:12px; padding:12px 14px; white-space:pre-line; word-break:break-all; }
    .at-receipt { max-width:560px; margin:0 auto; }
    .at-receipt .big { font-size:30px; font-weight:800; letter-spacing:-.02em; }
    .at-kv { display:flex; justify-content:space-between; gap:16px; padding:9px 0; border-bottom:1px solid var(--vz-border-color,#eff2f7); font-size:13.5px; }
    .at-kv:last-child { border-bottom:0; }
    .at-kv span:first-child { color:var(--vz-secondary-color,#878a99); }
    .at-kv span:last-child { font-weight:600; text-align:right; word-break:break-word; }
    .at-bars { display:flex; align-items:flex-end; gap:6px; height:120px; }
    .at-bars div { flex:1; background:linear-gradient(180deg,#0B8457,#3fb98a); border-radius:6px 6px 2px 2px; min-height:3px; position:relative; }
    .at-bars div:hover::after { content:attr(data-tip); position:absolute; bottom:calc(100% + 4px); left:50%; transform:translateX(-50%); background:#0f172a; color:#fff; font-size:11px; padding:3px 6px; border-radius:6px; white-space:nowrap; }
    .at-mono { font-family:ui-monospace,SFMono-Regular,Menlo,monospace; font-size:12.5px; }
</style>
