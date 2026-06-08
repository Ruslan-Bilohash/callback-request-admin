document.addEventListener('DOMContentLoaded', function () {
    const tabs = document.querySelectorAll('.cra-nav-tab');
    const contents = document.querySelectorAll('.cra-tab-content');

    if (!tabs.length) return;

    tabs.forEach(tab => {
        tab.addEventListener('click', function (e) {
            e.preventDefault();

            // Знімаємо активний клас з усіх табів
            tabs.forEach(t => t.classList.remove('cra-nav-tab-active'));

            // Ховаємо всі контент блоки
            contents.forEach(c => c.style.display = 'none');

            // Активуємо поточний таб
            this.classList.add('cra-nav-tab-active');

            // Показуємо відповідний контент
            const target = this.dataset.tab;
            const content = document.getElementById('tab-' + target);
            if (content) {
                content.style.display = 'block';
            }
        });
    });
});