(() => {
    let busy = false;
    const refresh = async () => {
        if (busy || document.hidden) return;
        busy = true;
        try {
            const response = await fetch('admin_resources.php?summary=1', {credentials: 'same-origin', cache: 'no-store'});
            if (!response.ok || response.redirected) return;
            const data = await response.json();
            if (!Number.isSafeInteger(data.pending) || data.pending < 0) return;
            document.querySelectorAll('[data-resource-count]').forEach(el => { el.textContent = String(data.pending); });
            document.querySelectorAll('[data-resource-notice]').forEach(el => { el.hidden = data.pending === 0; });
        } catch { /* Keep the last known count available when offline. */ }
        finally { busy = false; }
    };
    setInterval(refresh, 60000);
    document.addEventListener('visibilitychange', refresh);
})();
