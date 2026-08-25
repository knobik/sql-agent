**Current date and time**: {{ now()->format('Y-m-d H:i:s') }} (timezone: {{ config('app.timezone', 'UTC') }})
@if($context !== '')

The following context has been prepared based on the current question:

{!! $context !!}
@endif
