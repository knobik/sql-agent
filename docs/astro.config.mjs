// @ts-check
import { defineConfig } from 'astro/config';
import starlight from '@astrojs/starlight';

// https://astro.build/config
export default defineConfig({
	site: 'https://knobik.github.io',
	base: '/laravel-sql-agent',
	integrations: [
		starlight({
			title: 'Laravel SQL Agent',
			social: [{ icon: 'github', label: 'GitHub', href: 'https://github.com/knobik/laravel-sql-agent' }],
			sidebar: [
				{
					label: 'Getting Started',
					items: [
						{ label: 'Introduction', slug: 'getting-started' },
						{ label: 'Installation', slug: 'installation' },
					],
				},
				{
					label: 'Guides',
					items: [
						{ label: 'Configuration', slug: 'guides/configuration' },
						{ label: 'Knowledge Base', slug: 'guides/knowledge-base' },
						{ label: 'LLM & Search Drivers', slug: 'guides/drivers' },
						{ label: 'Web Interface', slug: 'guides/web-interface' },
						{ label: 'Evaluation', slug: 'guides/evaluation' },
						{ label: 'Self-Learning', slug: 'guides/self-learning' },
					],
				},
				{
					label: 'Reference',
					items: [
						{ label: 'Artisan Commands', slug: 'reference/commands' },
						{ label: 'Programmatic API', slug: 'reference/api' },
						{ label: 'Events', slug: 'reference/events' },
						{ label: 'Database Support', slug: 'reference/database-support' },
					],
				},
				{
					label: 'Troubleshooting',
					slug: 'troubleshooting',
				},
			],
		}),
	],
});
