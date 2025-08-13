import { Page } from '@playwright/test';
import { requireEnv } from '@utils/env.utils';
import { step } from '@utils/stepDecorator.utils';
import LoginPage from '@poms/frontend/login.page';

class LoginAsCustomerStep
{
    public constructor(private readonly page: Page, private readonly browserName?: string) {}

    @step('Login as customer', { box: true })
    /**
     * Logs in as a customer using the configured account credentials.
     *
     * If a `returnTo` URL is provided, it navigates to the specified URL after logging in.
     *
     * @param {string} returnTo - The URL to navigate to after logging in.
     * @returns {Promise<void>} - A promise that resolves when the login process is complete.
     * @throws {Error} - Throws an error if the customer account credentials are not configured in the `.env` file.
     */
    public async execute(returnTo?: string): Promise<void>
    {
        const browserEngine = this.browserName?.toUpperCase() || 'UNKNOWN';
        const loginPage = new LoginPage(this.page);
        const emailInputValue = requireEnv(`MAGENTO_EXISTING_ACCOUNT_EMAIL_${browserEngine}`);
        const passwordInputValue = requireEnv('MAGENTO_EXISTING_ACCOUNT_PASSWORD');

        await loginPage.login(emailInputValue, passwordInputValue);

        if (returnTo !== undefined) {
            await this.page.goto(returnTo);
            await this.page.waitForLoadState('networkidle');
        }
    }
}

export default LoginAsCustomerStep;
