{{-- Styles and modal events of the Master Data modals; include after the component's root element. --}}

<style>
    .mc-checks { display: grid; grid-template-columns: repeat(auto-fill, minmax(170px, 1fr)); gap: 6px; padding: 10px; border: 1px solid #E5E7EB; border-radius: 10px; background: #fff; }
    .mc-checks.is-invalid { border-color: #DC2626; }
    .mc-check { display: flex; align-items: center; gap: 8px; padding: 6px 8px; border-radius: 8px; cursor: pointer; font-size: 13.5px; }
    .mc-check:hover { background: #F9FAFB; }
</style>

<script>
document.addEventListener('livewire:load', function () {
    [
        { name: 'master', id: 'masterModal' },
        { name: 'master-delete', id: 'masterDeleteModal' },
    ].forEach(config => {
        window.addEventListener(`open-${config.name}-modal`, () => {
            bootstrap.Modal.getOrCreateInstance(document.getElementById(config.id)).show();
        });

        window.addEventListener(`close-${config.name}-modal`, () => {
            const instance = bootstrap.Modal.getInstance(document.getElementById(config.id));
            if (instance) {
                instance.hide();
            }
            document.querySelectorAll('.modal-backdrop').forEach(backdrop => backdrop.remove());
            document.body.classList.remove('modal-open');
        });
    });
});
</script>
