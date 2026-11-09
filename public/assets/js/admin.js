'use strict';

// Reset is destructive to the per-game odds override, so confirm it before the
// form is submitted. The server still validates the CSRF token and the action;
// this is a convenience, not an access control.
document.querySelectorAll('.admin-form__reset').forEach(function (form) {
    form.addEventListener('submit', function (event) {
        if (!window.confirm('Reset this game to the global odds and availability defaults?')) {
            event.preventDefault();
        }
    });
});

// Keep one game panel open at a time when scanning the list. Opening a panel
// never submits anything; all persistence still goes through the form buttons.
const games = document.querySelectorAll('details.admin-game');
games.forEach(function (game) {
    game.addEventListener('toggle', function () {
        if (!game.open) {
            return;
        }
        games.forEach(function (other) {
            if (other !== game) {
                other.open = false;
            }
        });
    });
});
