# Contributing

Thank you for considering contributing to SQL Agent for Laravel!

## Setup

```bash
git clone https://github.com/YOUR_USERNAME/sql-agent.git
cd sql-agent
composer install
```

## Workflow

1. Fork and branch from `main`
2. Make your changes
3. Run the quality checks:

```bash
composer test           # Pest tests
composer format         # Laravel Pint
composer analyse        # PHPStan
```

4. Open a pull request

## Pull Requests

- Create an issue first for significant changes
- Write tests for new functionality or bug fixes
- Update documentation if your changes affect the public API
- All tests, formatting, and static analysis must pass

## License

By contributing, you agree that your contributions will be licensed under the Apache-2.0 License.
