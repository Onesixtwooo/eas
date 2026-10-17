(() => {
    const loader = document.getElementById('page-loader');
    if (!loader) return;

    let fallback;
    const hide = () => {
        window.clearTimeout(fallback);
        loader.classList.add('is-hidden');
    };
    const show = () => {
        loader.classList.remove('is-hidden');
        window.clearTimeout(fallback);
        fallback = window.setTimeout(hide, 12000);
    };

    if (document.readyState === 'complete') hide();
    else window.addEventListener('load', hide, { once: true });
    window.addEventListener('pageshow', hide);
    fallback = window.setTimeout(hide, 12000);

    document.addEventListener('click', event => {
        if (event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;
        const link = event.target instanceof Element ? event.target.closest('a[href]') : null;
        if (!link || link.hasAttribute('download') || (link.target && link.target !== '_self')) return;
        const destination = new URL(link.href, location.href);
        if (!['http:', 'https:'].includes(destination.protocol) || destination.origin !== location.origin) return;
        if (destination.pathname === location.pathname && destination.search === location.search && destination.hash) return;
        show();
    });

    document.addEventListener('submit', event => {
        if (!event.defaultPrevented && event.target.target !== '_blank') show();
    });
})();
