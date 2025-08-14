import { Page } from '@playwright/test';
import { requireEnv } from '@utils/env.utils';
import { step } from '@utils/stepDecorator.utils';
import MagentoAdminPage from '@poms/adminhtml/magentoAdmin.page';

class LogInAsAdministratorStep
{
    public constructor(private readonly page: Page) {}

    @step('Log in as administrator', { box: true })
    /**
     * Logs into the Magento Admin using the configured adminstrator credentials.
     *
     * @returns {Promise<void>} - A promise that resolves when the login process is complete.
     * @throws {Error} - Throws an error if the administrator credentials are not configured in the `.env` file.
     */
    public async login(): Promise<void>
    {
        const adminPage = new MagentoAdminPage(this.page);
        const adminUsername = requireEnv('MAGENTO_ADMIN_USERNAME');
        const adminPassword = requireEnv('MAGENTO_ADMIN_PASSWORD');

        await adminPage.login(adminUsername, adminPassword);
    }
}

export default LogInAsAdministratorStep;
