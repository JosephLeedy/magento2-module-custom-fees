import { expect } from '@playwright/test';
import { UIReference } from '@config';
import BaseProductPage from 'base-tests/poms/frontend/product.page';

class ProductPage extends BaseProductPage
{
    public async addSimpleProductToCart(product: string, url: string, quantity?: string): Promise<void>
    {
        await this.page.goto(url);

        this.simpleProductTitle = this.page.getByRole('heading', { name: product, exact: true });

        expect(await this.simpleProductTitle.innerText()).toEqual(product);

        await expect(this.simpleProductTitle.locator('span')).toBeVisible();

        if (quantity !== undefined) {
            // set quantity
            await this.page.getByLabel(UIReference.productPage.quantityFieldLabel).fill(quantity);
        }

        await this.addToCartButton.click();

        await expect(this.page.getByRole('alert').locator(UIReference.general.messageLocator)).toBeVisible();
    }
}

export default ProductPage;
