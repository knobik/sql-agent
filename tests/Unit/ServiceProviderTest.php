<?php

test('config is loaded', function () {
    expect(config('sql-agent'))->toBeArray();
    expect(config('sql-agent.name'))->toBe('SqlAgent');
});

test('config has all required sections', function () {
    $config = config('sql-agent');

    expect($config)->toHaveKey('database');
    expect($config)->toHaveKey('llm');
    expect($config)->toHaveKey('search');
    expect($config)->toHaveKey('agent');
    expect($config)->toHaveKey('learning');
    expect($config)->toHaveKey('knowledge');
    expect($config)->toHaveKey('ui');
    expect($config)->toHaveKey('sql');
});

test('install command is registered', function () {
    $this->artisan('sql-agent:install', ['--help' => true])
        ->assertExitCode(0);
});

test('load-knowledge command is registered', function () {
    $this->artisan('sql-agent:load-knowledge', ['--help' => true])
        ->assertExitCode(0);
});

test('eval command is registered', function () {
    $this->artisan('sql-agent:eval', ['--help' => true])
        ->assertExitCode(0);
});

test('facade resolves correctly', function () {
    expect(app('sql-agent'))->toBeInstanceOf(\Knobik\SqlAgent\Agent\SqlAgent::class);
});
