@php($demoHelp = app(\App\Support\DemoHelp::class)->forRequest(request()))
@if($demoHelp)
    <details class="demo-help" data-demo-help="{{ $demoHelp['topic'] }}">
        <summary class="demo-help__summary">
            <svg class="demo-help__icon" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><circle cx="12" cy="12" r="9"/><path stroke-linecap="round" d="M12 11v5M12 7.5h.01"/></svg>
            <span>Más información</span>
            <svg class="demo-help__chevron" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><path stroke-linecap="round" stroke-linejoin="round" d="m6 9 6 6 6-6"/></svg>
        </summary>
        <div class="demo-help__content">
            <h2>{{ $demoHelp['title'] }}</h2>
            @foreach($demoHelp['paragraphs'] as $paragraph)
                <p>{{ $paragraph }}</p>
            @endforeach
            @if(!empty($demoHelp['tip']))
                <p class="demo-help__tip"><strong>Para empezar:</strong> {{ $demoHelp['tip'] }}</p>
            @endif
        </div>
    </details>
@endif
