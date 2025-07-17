# Functional Tests for Custom Fees

## Requirements

- Node.js 20+
- An environment with Magento Open Source 2.4.4+, Adobe Commerce 2.4.4+, or MageOS 1.0+ installed with sample data or 
  real data
- A Luma or Hyvä-based theme
- [Elgentos Playwright testing suite]

## Installation

1. Install the extension in an existing store by running these commands:

        cd /path/to/your/store
        composer require joseph-leedy/module-custom-fees
        php bin/magento module:enable JosephLeedy_CustomFees
        php bin/magento setup:upgrade
        php bin/magento setup:di:compile
        php bin/magento setup:static-content:deploy
2. Navigate to the path where the functional tests are located:

        cd vendor/joseph-leedy/module-custom-fees/test/Functional
3. Install Playwright, the Elgentos Test Suite, and other dependencies:

        npm install
4. Copy `.env.example` to `.env` and edit the variables inside to match your 
   environment

        rm -f .env && cp .env.example .env
5. Copy files from the `base-tests/config` directory into `tests/config` and 
   modify as needed to match your environment (_optional_)
6. Install the Playwright browsers (if running tests locally)

        npm run build

### Setting Up Warden To Run Tests With Playwright UI

If you're Magento environment is running inside of a [Warden] Docker container, you may also want to run the 
[Playwright UI] in separate container rather than running the tests locally on your machine. To do so, add the 
following settings to your `.env` file (found in the root of your Warden project):

```dotenv
PLAYWRIGHT_VERSION=v1.54.1-noble
PLAYWRIGHT_TEST_DIR=vendor/joseph-leedy/module-custom-fees/test/Functional
```

**Note:** Make sure that `PLAYWRIGHT_VERSION` matches the version number defined in
`vendor/joseph-leedy/module-custom-fees/test/Functional/package.json`.

After adding these settings, create a `.warden/warden-env.yml` if you do not already have one and add the following
Docker Compose configuration to it:

```yaml
services:
  playwright:
    depends_on:
      - nginx
    hostname: ${WARDEN_ENV_NAME}-playwright
    image: mcr.microsoft.com/playwright:${PLAYWRIGHT_VERSION}
    labels:
      - traefik.enable=true
      - traefik.http.routers.${WARDEN_ENV_NAME}-playwright.tls=true
      - traefik.http.routers.${WARDEN_ENV_NAME}-playwright.rule=Host(`playwright.${TRAEFIK_DOMAIN}`)
      - traefik.http.services.${WARDEN_ENV_NAME}-playwright.loadbalancer.server.port=3001
      - traefik.docker.network=${WARDEN_ENV_NAME}_default
    working_dir: /data
    command: /bin/sh -c "npx -y playwright test --ui-host 0.0.0.0 --ui-port 3001"
    init: true
    ipc: host
    environment:
      CI: true
    extra_hosts:
      - ${TRAEFIK_DOMAIN}:${TRAEFIK_ADDRESS:-0.0.0.0}
      - ${TRAEFIK_SUBDOMAIN:-app}.${TRAEFIK_DOMAIN}:${TRAEFIK_ADDRESS:-0.0.0.0}
    volumes:
      - .${WARDEN_WEB_ROOT:-}/${PLAYWRIGHT_TEST_DIR}/:/data:cached
```

Lastly, run `docker compose up playwright` to start the Docker container. The Playwright UI is accesible in your Web 
browser at https\://playwright.$WARDEN_ENV.test/ (e.g. https://playwright.magento.test/).

## Usage

### Before First Run

1. Apply patches to the Elgentos Testing Suite code:

        patch -p0 < patches/playwright.config.ts.patch
        patch -p0 < patches/bypass-captcha.config.ts.patch
        patch -p0 < patches/register.page.ts.patch
2. Create test customer accounts and run other setup tasks by running the following command:

        npm run test -- --grep @setup

### Running the tests

You can run the tests from the Playwright UI, or run the following command:

    npm run test:all

## Additional Information

For more information regarding the usage of Playwright, please see the [Playwright documentation].

[Elgentos Playwright testing suite]: https://github.com/elgentos/magento2-playwright
[Warden]: https://warden.dev
[Playwright UI]: https://playwright.dev/docs/test-ui-mode
[Playwright Documentation]: https://playwright.dev/docs/intro
