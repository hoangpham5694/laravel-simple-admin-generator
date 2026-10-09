// Run with a Playwright page and the contents of navbar-search.js.
async function testNavbarSearch(page, source) {
    await page.route('https://example.test/**', route => route.fulfill({body: '<html></html>', contentType: 'text/html'}));
    await page.goto('https://example.test/');
    await page.setContent(`<div data-sag-navbar-search>
        <form action="https://example.test/admin/search" method="GET">
            <input type="search" name="q" data-sag-search-input data-url="https://example.test/admin/search/suggestions" data-min-length="2" data-max-length="20" data-debounce="40" aria-expanded="false">
            <button type="submit">Search</button><button type="button" data-widget="navbar-search">Close</button>
            <div data-sag-search-results id="sag-search-suggestions" hidden></div>
        </form></div><input id="sidebar" type="search" name="q"><button id="outside">Outside</button>`);
    await page.evaluate(() => {
        window.requests = [];
        window.fetch = (url, options) => new Promise((resolve, reject) => {
            window.requests.push({url, options, resolve, reject});
        });
    });
    await page.addScriptTag({content: source});
    const input = page.locator('[data-sag-search-input]');
    const results = page.locator('[data-sag-search-results]');
    let checks = 0;
    async function check(condition, message) {
        if (!condition) throw new Error(message);
        checks++;
    }
    async function settle() { await page.waitForTimeout(70); }
    async function reply(index, data, ok = true) {
        await page.evaluate(({index, data, ok}) => window.requests[index].resolve({ok, json: async () => data}), {index, data, ok});
        await page.waitForTimeout(10);
    }
    await page.locator('#sidebar').fill('sidebar word');
    await input.fill('a');
    await settle();
    await check(await page.evaluate(() => requests.length === 0), 'Short query or sidebar issued AJAX');
    await input.evaluate(element => {
        element.value = 'first'; element.dispatchEvent(new Event('input'));
        element.value = 'second'; element.dispatchEvent(new Event('input'));
    });
    await settle();
    await check(await page.evaluate(() => requests.length === 1 && new URL(requests[0].url).searchParams.get('q') === 'second'), 'Debounce failed');
    await check(await results.textContent() === 'Searching…View search results', 'Missing loading state');
    await input.fill('third');
    await settle();
    await check(await page.evaluate(() => requests[0].options.signal.aborted), 'Changed query did not abort');
    await reply(1, {results: [{title: '<img src=x onerror=alert(1)>', description: '<b>text</b>', provider_label: '<label>', url: '/admin/item/1'}, {title: 'unsafe', url: 'javascript:alert(1)'}]});
    await check(await results.locator('a').count() === 2 && await results.locator('img,b').count() === 0, 'Escaping or URL rejection failed');
    await reply(0, {results: [{title: 'STALE', url: '/old'}]});
    await check(!(await results.textContent()).includes('STALE'), 'Stale response replaced current results');
    await input.focus();
    await page.keyboard.press('Tab');
    await check(await page.evaluate(() => document.activeElement.type === 'submit'), 'Submit is not keyboard accessible');
    await page.keyboard.press('Tab');
    await page.keyboard.press('Tab');
    await check(await page.evaluate(() => document.activeElement.tagName === 'A'), 'Suggestion links are not keyboard accessible');
    await input.fill('special &?');
    await settle();
    const last = await page.evaluate(() => requests.length - 1);
    await reply(last, {results: []});
    await check((await results.textContent()).includes('No results found'), 'Missing empty state');
    await check(new URL(await results.locator('a').last().getAttribute('href')).searchParams.get('q') === 'special &?', 'View-results query is not encoded');
    await input.fill('error');
    await settle();
    await reply(await page.evaluate(() => requests.length - 1), {}, false);
    await check((await results.textContent()).includes('Unable to load'), 'Missing error state');
    await input.fill('pending');
    await settle();
    const pending = await page.evaluate(() => requests.length - 1);
    await page.locator('[data-widget="navbar-search"]').click();
    await reply(pending, {results: [{title: 'LATE', url: '/late'}]});
    await check(await results.isHidden(), 'Close allowed late response to reopen');
    await input.fill('escape');
    await settle();
    await input.press('Escape');
    await check(await results.isHidden(), 'Escape did not close');
    await input.fill('outside');
    await settle();
    await page.locator('#outside').click();
    await check(await results.isHidden(), 'Outside click did not close');
    await input.fill('clear');
    await settle();
    await input.fill('');
    await check(await results.isHidden(), 'Clear did not close');
    const beforeComposition = await page.evaluate(() => requests.length);
    await input.dispatchEvent('compositionstart');
    await input.fill('composing');
    await settle();
    await check(await page.evaluate(() => requests.length) === beforeComposition, 'Composition issued request');
    await input.dispatchEvent('compositionend');
    await settle();
    await check(await page.evaluate(() => requests.length) === beforeComposition + 1, 'Composition end did not search');
    await page.evaluate(() => {
        window.submitted = false;
        document.querySelector('form').addEventListener('submit', event => { window.submitted = true; event.preventDefault(); });
    });
    await input.press('Enter');
    await check(await page.evaluate(() => submitted) && await results.isHidden(), 'Enter did not submit during AJAX');
    return {checks};
}
