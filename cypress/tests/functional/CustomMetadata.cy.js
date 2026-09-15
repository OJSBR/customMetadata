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
 * Parameters (--env): contextPath, adminUser, adminPassword (a press manager)
 * and submissionId (a submission whose current publication can be edited).
 * Captcha on login must be off for the run. The plugin must be enabled.
 * Selectors use ids and names, so the spec runs against a press in any
 * language. The definitions and the values touched are put back at the end.
 */

describe('Custom Metadata plugin', function() {
	const contextPath = Cypress.env('contextPath') || 'publicknowledge';
	const adminUser = Cypress.env('adminUser') || 'admin';
	const adminPassword = Cypress.env('adminPassword') || 'admin';
	const submissionId = Cypress.env('submissionId') || 1;

	const settingsForm = 'form[id="customMetadataSettingsForm"]';
	const definition = settingsForm + ' textarea[name="customFieldsDefinition"]';
	const field = (key) => '[id="metadata-' + key + '-control"]';
	const testDefinition = "# review test\ntitle | Title again | text\nreviewIsbn | Review ISBN | text\nreviewIsbn | Repeated | textarea\nreviewNote | Review note | textarea";

	// Signs in through requests: the login page can re-render while it is typed into.
	const login = () => {
		cy.clearCookies();
		cy.request('/index.php/' + contextPath + '/login').then((response) => {
			const token = /name="csrfToken" value="([^"]+)"/.exec(response.body)[1];
			// OMP 3.5 redirects to a URL with the language, which would turn the POST into a GET.
			const action = /<form[^>]*id="login"[^>]*action="([^"]+)"/.exec(response.body)[1];
			cy.request({
				method: 'POST',
				url: action,
				form: true,
				body: {csrfToken: token, username: adminUser, password: adminPassword},
				log: false,
			});
		});
		cy.request('/index.php/' + contextPath + '/management/settings/website').its('body').should('not.contain', 'id="login"');
	};

	const openSettings = () => {
		// A new query string forces a page load; the hash opens the Plugins tab.
		cy.visit('/index.php/' + contextPath + '/management/settings/website?reload=' + Date.now() + '#plugins');
		cy.get('button[id="plugins-button"]', {timeout: 60000}).should('have.attr', 'aria-selected', 'true');
		cy.waitJQuery();
		cy.get('tr[id*="custommetadataplugin"] a.show_extras', {timeout: 30000}).should('be.visible').click();
		cy.get('a[id*="custommetadataplugin-settings"]', {timeout: 30000}).click();
		cy.waitJQuery();
		cy.get(definition, {timeout: 30000}).should('exist');
		// Submitting before the form handler is bound posts the page itself.
		cy.window().should((win) => {
			expect(win.jQuery(settingsForm).data('pkp.handler')).to.exist;
		});
	};

	const saveDefinition = (text) => {
		openSettings();
		cy.get(definition).invoke('val', text);
		cy.get(settingsForm + ' button[id^="submitFormButton-"]').click({force: true});
		cy.waitJQuery();
		cy.get(settingsForm).should('not.exist');
	};

	// OMP 3.4 opens the workflow page with Publication > Metadata tabs; OMP 3.5
	// opens the submission in the editorial dashboard, on the menu item asked for.
	const openMetadata = () => {
		cy.visit('/index.php/' + contextPath + '/workflow/access/' + submissionId + '?reload=' + Date.now());
		cy.get('button[id="publication-button"], .pkpWorkspace, [class*="SideMenu"], nav', {timeout: 60000});
		cy.location('pathname').then((pathname) => {
			if (pathname.includes('/dashboard/')) {
				cy.visit('/index.php/' + contextPath + '/dashboard/editorial?workflowSubmissionId=' + submissionId + '&workflowMenuKey=publication_metadata');
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

	let original = '';

	before(function() {
		login();
		openSettings();
		cy.get(definition).invoke('val').then((value) => {
			original = value || '';
		});
	});

	it('Shows only the new, distinct keys and keeps what is saved', function() {
		login();
		saveDefinition(testDefinition);
		openSettings();
		cy.get(definition).should('have.value', testDefinition);
		cy.get(settingsForm + ' p.description').invoke('text').should('match', /\S/).and('not.contain', '##');

		openMetadata();
		cy.get(field('reviewIsbn')).should('have.length', 1).and('match', 'input');
		cy.get(field('reviewNote')).should('have.length', 1).and('match', 'textarea');
		cy.get('[name="reviewIsbn"], [name^="reviewNote"]').should('have.length', 2);
		cy.get('label:contains("Title again"), label:contains("Repeated")').should('have.length', 0);

		cy.get(field('reviewIsbn')).clear().type('978-0-00-000000-0', {delay: 0});
		saveMetadata();
		openMetadata();
		cy.get(field('reviewIsbn')).should('have.value', '978-0-00-000000-0');
		cy.get(field('reviewIsbn')).type('{selectall}{backspace}', {delay: 0}).should('have.value', '');
		saveMetadata();
	});

	after(function() {
		login();
		saveDefinition(original);
	});
});
