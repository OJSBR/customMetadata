/**
 * @file cypress/tests/functional/CustomMetadata.cy.js
 *
 * Copyright (c) 2026 OJSBR (https://ojsbr.com)
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * Functional tests: the field definitions are saved from the plugin settings,
 * and the fields they declare appear on the publication Metadata tab and keep
 * what the editor saves there.
 *
 * Parameters (--env): contextPath, adminUser, adminPassword (captcha on login
 * must be off for the run) and submissionId (a submission whose current
 * publication can be edited; default 1). The defaults match the data set of
 * PKP's continuous integration, and the first test enables the plugin when it is
 * off. Selectors use ids and names, so the spec runs against a press in any
 * language. The definitions and the values touched are put back at the end.
 */

describe('Custom Metadata plugin', function() {
	const contextPath = Cypress.env('contextPath') || 'publicknowledge';
	const adminUser = Cypress.env('adminUser') || 'admin';
	const adminPassword = Cypress.env('adminPassword') || 'admin';
	const submissionId = Cypress.env('submissionId') || 1;

	const rowName = 'custommetadataplugin';
	const settingsForm = 'form[id="customMetadataSettingsForm"]';
	const definition = settingsForm + ' textarea[name="customFieldsDefinition"]';
	const field = (key) => '[id="metadata-' + key + '-control"]';
	const testDefinition = "# review test\ntitle | Title again | text\nreviewIsbn | Review ISBN | text\nreviewIsbn | Repeated | textarea\nreviewNote | Review note | textarea";
	let original = null;

	// ---- OJSBR spec helpers (padrão v2): work on OJS/OMP 3.3, 3.4 and 3.5 and in PKP's CI ----

	const pageUrl = (path) => '/index.php/' + contextPath + (path ? '/' + path : '');

	// Same as PKP's cy.waitJQuery(), which the support files of OJS 3.3 test sites may lack.
	// The Plugins tab can keep requests open for a while (the plugin gallery), hence the timeout.
	const waitJQuery = () => cy.window().its('jQuery.active', {timeout: 60000}).should('eq', 0);

	// Requests carry the browser's User-Agent: OJS 3.3 drops a session whose agent changes.
	const request = (options) => cy.window({log: false}).then((win) => cy.request(Object.assign(
		typeof options === 'string' ? {url: options} : options,
		{headers: Object.assign({'User-Agent': win.navigator.userAgent}, (typeof options === 'string' ? {} : options.headers) || {})}
	)));

	// Signs in through requests (the login page can re-render while it is typed into), then
	// falls back to the form when the session did not stick (OJS 3.3 cookie handling).
	const login = (username, password) => {
		cy.clearCookies();
		request(pageUrl('login')).then((response) => {
			const token = /name="csrfToken" value="([^"]+)"/.exec(response.body)[1];
			// The form posts to the URL with the language: a redirect would turn the POST into a GET.
			const action = /<form[^>]*id="login"[^>]*action="([^"]+)"/.exec(response.body)[1];
			request({method: 'POST', url: action, form: true, body: {csrfToken: token, username: username, password: password}, log: false});
		});
		cy.visit(pageUrl('submissions') + '?reload=' + Date.now());
		cy.get('body').then(($body) => {
			if ($body.find('form#login').length) {
				cy.get('form#login input[name="username"]').type(username, {delay: 0});
				cy.get('form#login input[name="password"]').type(password, {delay: 0, log: false});
				cy.get('form#login').submit();
				cy.get('form#login', {timeout: 30000}).should('not.exist');
			}
		});
	};

	// REST API calls made from the page itself, so they carry the browser's own session.
	const api = (path, options = {}) => cy.window({log: false}).then((win) => cy.wrap(
		win.fetch(path, Object.assign({credentials: 'same-origin'}, options)).then((response) => {
			if (!response.ok) {
				return response.text().then((text) => {
					throw new Error(path + ' answered ' + response.status + ': ' + text.slice(0, 300));
				});
			}
			return response.json();
		}),
		{log: false, timeout: 30000}
	));

	// The website settings page on its Plugins tab (a new query string forces a load). Load it
	// once per test: loading it again while its plugin gallery request is pending stalls the
	// web server of PKP's CI; API calls and settings modals work on the page already open.
	const openPluginsTab = () => {
		cy.visit(pageUrl('management/settings/website') + '?reload=' + Date.now() + '#plugins');
		cy.get('button[id="plugins-button"]', {timeout: 60000}).click();
		cy.get('button[id="plugins-button"]').should('have.attr', 'aria-selected', 'true');
		waitJQuery();
	};

	// Enables the plugin in the grid when it is off (never turns it off).
	const enablePlugin = (rowName) => {
		cy.get('input[id^="select-cell-' + rowName + '-enabled"]', {timeout: 30000}).then(($checkbox) => {
			if (!$checkbox.is(':checked')) {
				cy.wrap($checkbox).click();
				waitJQuery();
			}
		});
		cy.get('input[id^="select-cell-' + rowName + '-enabled"]').should('be.checked');
	};

	// Opens the settings modal from the grid, without reloading the page: a reload right
	// after saving can stall the web server of PKP's CI. The form is fetched each time.
	const openPluginSettings = (rowName, formSelector) => {
		cy.get('a[id*="-row-' + rowName + '-settings-button-"]', {timeout: 30000}).then(($link) => {
			if (!$link.is(':visible')) {
				cy.get('tr[id$="-row-' + rowName + '"] a.show_extras').first().click();
			}
		});
		// The grid may still be animating the extras row: the link is clicked once it exists.
		cy.get('a[id*="-row-' + rowName + '-settings-button-"]').first().click({force: true});
		waitJQuery();
		cy.window().should((win) => {
			expect(win.jQuery(formSelector).data('pkp.handler')).to.exist;
		});
	};

	// ---- end of helpers ----

	const openSettings = () => openPluginSettings(rowName, settingsForm);

	const saveDefinition = (text) => {
		cy.get(definition).invoke('val', text);
		cy.get(settingsForm + ' button[id^="submitFormButton-"]').click({force: true});
		waitJQuery();
		cy.get(settingsForm).should('not.exist');
	};

	// OMP 3.4 opens the workflow page with Publication > Metadata tabs; OMP 3.5
	// opens the submission in the editorial dashboard, on the menu item asked for.
	const openMetadata = () => {
		cy.visit(pageUrl('workflow/access/' + submissionId) + '?reload=' + Date.now());
		cy.get('button[id="publication-button"], .pkpWorkspace, [class*="SideMenu"], nav', {timeout: 60000});
		cy.location('pathname').then((pathname) => {
			if (pathname.includes('/dashboard/')) {
				cy.visit(pageUrl('dashboard/editorial') + '?workflowSubmissionId=' + submissionId + '&workflowMenuKey=publication_metadata');
			} else {
				cy.get('button[id="publication-button"]').click();
				cy.get('button[id="metadata-button"]', {timeout: 30000}).click();
			}
		});
		cy.get(field('reviewIsbn'), {timeout: 60000}).scrollIntoView().should('be.visible');
	};

	const saveMetadata = () => {
		// Saved with POST and a method override.
		cy.intercept('**/api/v1/submissions/*/publications/*').as('savePublication');
		cy.get(field('reviewIsbn')).closest('form').find('.pkpFormPage__footer button, button[type="submit"]').last().click();
		cy.wait('@savePublication').its('response.statusCode').should('eq', 200);
	};

	it('Enables the plugin and saves the field definitions', function() {
		login(adminUser, adminPassword);
		openPluginsTab();
		enablePlugin(rowName);
		openSettings();
		cy.get(definition).invoke('val').then((value) => {
			original = value || '';
		});
		saveDefinition(testDefinition);
		openSettings();
		cy.get(definition).should('have.value', testDefinition);
		cy.get(settingsForm + ' p.description').invoke('text').should('match', /\S/).and('not.contain', '##');
	});

	it('Shows only the new, distinct keys on the Metadata tab and saves a value', function() {
		login(adminUser, adminPassword);
		openMetadata();
		cy.get(field('reviewIsbn')).should('have.length', 1).and('match', 'input');
		cy.get(field('reviewNote')).should('have.length', 1).and('match', 'textarea');
		cy.get('[name="reviewIsbn"], [name^="reviewNote"]').should('have.length', 2);
		cy.get('label:contains("Title again"), label:contains("Repeated")').should('have.length', 0);
		cy.get(field('reviewIsbn')).clear().type('978-0-00-000000-0', {delay: 0});
		saveMetadata();
	});

	it('Keeps the saved value', function() {
		login(adminUser, adminPassword);
		openMetadata();
		cy.get(field('reviewIsbn')).should('have.value', '978-0-00-000000-0');
		// Put it back as it was.
		cy.get(field('reviewIsbn')).type('{selectall}{backspace}', {delay: 0}).should('have.value', '');
		saveMetadata();
	});

	it('Puts the definitions back', function() {
		if (original === null) {
			return;
		}
		login(adminUser, adminPassword);
		openPluginsTab();
		openSettings();
		saveDefinition(original);
	});
});
