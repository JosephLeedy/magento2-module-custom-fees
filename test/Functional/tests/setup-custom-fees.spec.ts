// @ts-check

import { test as base } from '@playwright/test';
import { toggles } from '@config';
import { requireEnv } from '@utils/env.utils';
import CustomFeesConfigurationPage from '@poms/adminhtml/customFeesConfiguration.page';

/**
 * NOTE:
 * The first if-statement checks if we are running in CI.
 * If so, we always run the setup.
 * Else, we check if the 'setup' test toggle in test-toggles.json has been set to true.
 */

const runSetupTests = (describeFn: typeof base.describe | typeof base.describe.only) => {
    describeFn('Setting up the testing environment', () => {
        base('Configure custom fees', { tag: '@setup' }, async ({ page, browserName }, testInfo) => {
            const magentoAdminUsername = requireEnv('MAGENTO_ADMIN_USERNAME');
            const magentoAdminPassword = requireEnv('MAGENTO_ADMIN_PASSWORD');
            const browserEngine = browserName?.toUpperCase() || 'UNKNOWN';

            if (browserEngine === "CHROMIUM") {
                const customFeesConfigurationPage = new CustomFeesConfigurationPage(page);

                await customFeesConfigurationPage.login(magentoAdminUsername, magentoAdminPassword);
                await customFeesConfigurationPage.configureCustomFees();
            } else {
                testInfo.skip(true, `Skipping because configuration is only needed once.`);
            }
        });
    });
};

if (process.env.CI) {
    runSetupTests(base.describe);
} else if (toggles.general.setup) {
    runSetupTests(base.describe.only);
}
