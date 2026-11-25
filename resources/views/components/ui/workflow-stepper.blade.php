@props([
    'steps',
    'current' => 1,
    'label' => 'Progreso del formulario',
    'interactive' => true,
])

<nav
    class="workflow-stepper"
    aria-label="{{ $label }}"
    data-workflow-stepper
    data-current-step="{{ $current }}"
>
    <ol class="workflow-stepper__list">
        @foreach ($steps as $step)
            @php
                $number = (int) $step['number'];
                $state = $number === $current
                    ? 'current'
                    : ($step['state'] ?? ($number < $current ? 'completed' : 'pending'));
            @endphp

            <li
                class="workflow-step workflow-step--{{ $state }}"
                data-workflow-step="{{ $number }}"
            >
                @php($stepHref = $step['href'] ?? null)
                @if (($step['available'] ?? true) === false)
                <span class="workflow-step__button" aria-disabled="true">
                    <span class="workflow-step__indicator" aria-hidden="true">
                        <span class="workflow-step__number">{{ $number }}</span>
                        <span class="workflow-step__check"><x-ui.icon name="check" :size="15" /></span>
                    </span>
                    <span class="workflow-step__copy">
                        <strong>{{ $step['name'] }}</strong>
                        <small>{{ $step['description'] }}</small>
                    </span>
                </span>
                @elseif ($stepHref)
                <a
                    href="{{ $stepHref }}"
                    class="workflow-step__button"
                    data-step-number="{{ $number }}"
                    @if ($state === 'current') aria-current="step" @endif
                >
                    <span class="workflow-step__indicator" aria-hidden="true">
                        <span class="workflow-step__number">{{ $number }}</span>
                        <span class="workflow-step__check">
                            <x-ui.icon name="check" :size="15" />
                        </span>
                    </span>

                    <span class="workflow-step__copy">
                        <strong>{{ $step['name'] }}</strong>
                        <small>{{ $step['description'] }}</small>
                    </span>
                </a>
                @else
                <button
                    type="button"
                    class="workflow-step__button"
                    data-workflow-step-button
                    data-workflow-step-link
                    data-step-number="{{ $number }}"
                    data-step-target="{{ $step['target'] }}"
                    @disabled(! $interactive || $number > $current)
                    @if ($state === 'current') aria-current="step" @endif
                >
                    <span class="workflow-step__indicator" aria-hidden="true">
                        <span class="workflow-step__number">{{ $number }}</span>
                        <span class="workflow-step__check">
                            <x-ui.icon name="check" :size="15" />
                        </span>
                    </span>

                    <span class="workflow-step__copy">
                        <strong>{{ $step['name'] }}</strong>
                        <small>{{ $step['description'] }}</small>
                    </span>
                </button>
                @endif
            </li>
        @endforeach
    </ol>
</nav>
