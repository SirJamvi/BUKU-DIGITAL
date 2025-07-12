<div class="table-responsive">
    <table {{ $attributes->merge(['class' => 'table table-bordered table-hover']) }}>
        @if (isset($thead))
            <thead class="thead-light">
                {{ $thead }}
            </thead>
        @endif
        
        <tbody>
            {{ $slot }}
        </tbody>

        @if (isset($tfoot))
            <tfoot>
                {{ $tfoot }}
            </tfoot>
        @endif
    </table>
</div>