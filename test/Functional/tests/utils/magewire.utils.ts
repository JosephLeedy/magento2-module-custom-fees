import BaseMagewireUtils from 'base-tests/utils/magewire.utils';

class MagewireUtils extends BaseMagewireUtils
{
    /**
     * Waits for all Magewire requests to complete
     * This includes both UI indicators and actual network requests
     */
    public async waitForMagewireRequests(): Promise<void>
    {
        // Wait for the Magewire messenger element to disappear or have 0 height
        await this.page.locator('.magewire\\.messenger').waitFor({ state: 'hidden', timeout: 30000 });

        // Additionally wait for any pending Magewire network requests to complete
        await this.page.waitForFunction(() => {
            return !window.magewire || !(window.magewire as any).processing;
        }, { timeout: 30000 });

        // Small additional delay to ensure DOM updates are complete
        await this.page.waitForTimeout(500);
    }
}

export default MagewireUtils;
