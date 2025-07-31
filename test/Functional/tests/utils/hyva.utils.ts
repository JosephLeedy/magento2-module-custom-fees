import type { Page } from '@playwright/test';

class HyvaUtils
{
    public constructor(private readonly page: Page) {}

    public async isHyva(): Promise<boolean>
    {
        return await this.page.evaluate((): boolean => document.body.classList.value.includes('hyva'));
    }
}

export default HyvaUtils;
