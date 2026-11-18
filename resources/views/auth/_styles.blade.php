@push('head')
<style>
    .auth { max-width: 420px; margin: 30px auto; padding: 28px; }
    .auth h1 { margin-bottom: 4px; }
    .auth form { display: flex; flex-direction: column; gap: 14px; margin-top: 20px; }
    .auth label { display: flex; flex-direction: column; gap: 5px; font-size: 14px; font-weight: 600; }
    .auth label.check { flex-direction: row; align-items: center; gap: 8px; font-weight: 500; }
    .auth input[type=text], .auth input[type=email], .auth input[type=password] {
        font: inherit; font-weight: 400; padding: 10px 12px; border-radius: 9px; border: 1px solid var(--border);
        background: var(--surface); color: var(--text); }
    .auth input:focus { outline: none; border-color: var(--accent); }
    .auth .err { color: var(--danger); font-size: 14px; }
    .auth .switch { margin: 18px 0 0; text-align: center; font-size: 14px; }
    .auth .switch a { color: var(--accent); font-weight: 600; }
</style>
@endpush
