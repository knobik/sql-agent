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

### Tooling versions

`composer.lock` is not committed, so every `composer install` resolves fresh. Pint, PHPStan and Larastan are therefore pinned to a patch range: they gate CI, and a new minor of any of them can add rules that fail the build with no code change.

Run `composer install` rather than `composer update` before the quality checks, so you are running the same versions CI is. Upgrades to a new minor arrive as a Dependabot pull request, where any resulting reformat or new static analysis errors can be reviewed and fixed together.

## Pull Requests

- Create an issue first for significant changes
- Write tests for new functionality or bug fixes
- Update documentation if your changes affect the public API
- All tests, formatting, and static analysis must pass

## License

By contributing, you agree that your contributions will be licensed under the Apache-2.0 License.
