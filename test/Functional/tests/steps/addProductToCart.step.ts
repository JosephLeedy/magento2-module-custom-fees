import { Page } from '@playwright/test';
import { slugs } from '@config';
import { step } from '@utils/stepDecorator.utils';
import ProductPage from '@poms/frontend/product.page';

class AddProductToCartStep
{
    public constructor(private readonly page: Page) {}

    @step('Add product to cart', { box: true })
    /**
     * Adds a product to the cart.
     *
     * @param {string} productType - The type of product to add (i.e. 'simple' or 'configurable').
     * @param {string} productTitle - The title of the product to add.
     * @param {string} productUrl - The URL of the product to add.
     * @param {string} [quantity] - The quantity of the product to add. (optional)
     * @returns {Promise<void>} - A promise that resolves when the product is added to the cart.
     * @throws {Error} - Throws an error if the product cannot be added to the cart.
     */
    public async execute(
        productType: 'simple' | 'configurable',
        productTitle: string,
        productUrl: string,
        quantity?: string
    ): Promise<void> {
        const productPage = new ProductPage(this.page);

        if (productType === 'simple') {
            await productPage.addSimpleProductToCart(productTitle, productUrl, quantity);
        }

        if (productType === 'configurable') {
            await productPage.addConfigurableProductToCart(productTitle, productUrl, quantity);
        }

        await this.page.goto(slugs.cart.cartSlug);
        await this.page.waitForLoadState('networkidle');
    }

    @step('Add simple product to cart', { box: true })
    /**
     * Adds a simple product to the cart.
     *
     * @param {string} productTitle - The title of the simple product to add.
     * @param {string} productUrl - The URL of the simple product to add.
     * @param {string} [quantity] - The quantity of the simple product to add. (optional)
     * @returns {Promise<void>} - A promise that resolves when the simple product is added to the cart.
     * @throws {Error} - Throws an error if the simple product cannot be added to the cart.
     */
    public async addSimpleProductToCart(productTitle: string, productUrl: string, quantity?: string): Promise<void>
    {
        await this.execute('simple', productTitle, productUrl, quantity);
    }

    @step('Add configurable product to cart', { box: true })
    /**
     * Adds a configurable product to the cart.
     *
     * @param {string} productTitle - The title of the configurable product to add.
     * @param {string} productUrl - The URL of the configurable product to add.
     * @param {string} [quantity] - The quantity of the configurable product to add. (optional)
     * @returns {Promise<void>} - A promise that resolves when the configurable product is added to the cart.
     * @throws {Error} - Throws an error if the configurable product cannot be added to the cart.
     */
    public async addConfigurableProductToCart(
        productTitle: string,
        productUrl: string,
        quantity?: string
    ): Promise<void> {
        await this.execute('configurable', productTitle, productUrl, quantity);
    }
}

export default AddProductToCartStep;
