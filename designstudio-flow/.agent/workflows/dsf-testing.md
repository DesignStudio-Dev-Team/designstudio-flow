---
description: Running and Adding Tests
---

# Testing Workflow

We use a combination of **Vitest** for frontend (Vue.js) and **PHPUnit** for backend (WordPress/PHP) testing.

## 🏃 Running Tests

### Frontend (Vue.js)

Run frontend unit tests:
```bash
// turbo
npm run test:run
```

Run tests in watch mode (for development):
```bash
// turbo
npm run test
```

### Backend (PHP)

Run backend unit tests:
```bash
// turbo
npm run test:php
```

## 🧪 Adding New Tests

### Frontend Components
1. Create a file named `YourComponent.spec.js` inside `src/components/__tests__/`.
2. Import `mount` from `@vue/test-utils`.
3. Write your tests using `describe` and `it`.

Example:
```javascript
import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import MyComponent from '../MyComponent.vue'

describe('MyComponent', () => {
  it('renders properly', () => {
    const wrapper = mount(MyComponent)
    expect(wrapper.text()).toContain('Hello World')
  })
})
```

### Backend Classes
1. Create a file named `Test_YourClass.php` inside `tests/`.
2. Extend `TestCase`.
3. Use `WP_Mock` to mock WordPress functions if needed.

Example:
```php
class Test_YourClass extends TestCase {
    public function setUp(): void {
        parent::setUp();
        WP_Mock::setUp();
    }
    
    public function tearDown(): void {
        WP_Mock::tearDown();
        parent::tearDown();
    }
    
    public function test_something() {
        $this->assertTrue(true);
    }
}
```

## 📦 Dependencies

- **Frontend**: `vitest`, `jsdom`, `@vue/test-utils`
- **Backend**: `phpunit`, `wp_mock`, `mockery`
