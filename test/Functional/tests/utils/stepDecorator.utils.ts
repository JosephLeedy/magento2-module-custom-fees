import { test } from '@playwright/test';
import { Location } from 'playwright/types/test';

export function step(name: string, options?: { box?: boolean, location?: Location, timeout?: number }) {
    return function stepDecorator(target: Function) {
        return function replacementMethod(this: any, ...args: any[]) {
            return test.step(name, async () => await target.call(this, ...args), options);
        };
    };
}
