/* Menu mobile */
(function () {
    var burger = document.getElementById('burger');
    var sidebar = document.getElementById('sidebar');

    if (burger && sidebar) {
        burger.addEventListener('click', function () {
            sidebar.classList.toggle('ouvert');
        });
    }
})();

/* Confirmation avant les actions sensibles : <form data-confirm="..."> */
document.querySelectorAll('form[data-confirm]').forEach(function (formulaire) {
    formulaire.addEventListener('submit', function (evenement) {
        if (!window.confirm(formulaire.dataset.confirm)) {
            evenement.preventDefault();
        }
    });
});

/* Liste de classes filtrée selon la filière choisie :
   <select data-filtre-cible="idClasse"> ... <option data-filiere="3"> */
document.querySelectorAll('select[data-filtre-cible]').forEach(function (source) {

    var cible = document.getElementById(source.dataset.filtreCible);

    if (!cible) {
        return;
    }

    function filtrer() {

        var filiere = source.value;

        Array.prototype.forEach.call(cible.options, function (option) {

            if (!option.value) {
                return;
            }

            var visible = !filiere || option.dataset.filiere === filiere;

            option.hidden = !visible;
            option.disabled = !visible;
        });

        var choix = cible.options[cible.selectedIndex];

        if (choix && choix.value && choix.disabled) {
            cible.value = '';
        }
    }

    source.addEventListener('change', filtrer);
    filtrer();
});

/* Bouton d'impression : <button data-imprimer> */
document.querySelectorAll('[data-imprimer]').forEach(function (bouton) {
    bouton.addEventListener('click', function () {
        window.print();
    });
});
