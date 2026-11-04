/**
 *------
 * BGA framework: Gregory Isabelli & Emmanuel Colin & BoardGameArena
 * Krestiki implementation : © <Your name here> <Your email address here>
 *
 * This code has been produced on the BGA studio platform for use on http://boardgamearena.com.
 * See http://en.boardgamearena.com/#!doc/Studio for more information.
 * -----
 *
 * In this file, you are describing the logic of your user interface, in Javascript language.
 *
 */

/**
 * @typedef {Object} PlayerTurnArgs
 * @property {number[]} playableCells
 */

const SYMBOL_GLYPHS = { X: '✕', O: '◯' };

/**
 * We create one State class per declared state on the PHP side, to handle all state specific code here.
 * onEnteringState, onLeavingState and onPlayerActivationChange are predefined names that will be called by the framework.
 * When executing code in this state, you can access the args using this.args
 */
class PlayerTurn {
    /** @type {PlayerTurnArgs} */
    args;

    /**
     * @param {Game} game
     * @param {Bga} bga
     */
    constructor(game, bga) {
        this.game = game;
        this.bga = bga;
    }

    /**
     * This method is called each time we are entering the game state. You can use this method to perform some user interface changes at this moment.
     *
     * @param {PlayerTurnArgs} args
     * @param {boolean} isCurrentPlayerActive
     */
    onEnteringState(args, isCurrentPlayerActive) {
        this.bga.statusBar.setTitle(isCurrentPlayerActive ?
            _('${you} must place a piece') :
            _('${actplayer} must place a piece')
        );

        if (isCurrentPlayerActive) {
            this.game.highlightPlayable(args.playableCells);
        }
    }

    /**
     * This method is called each time we are leaving the game state. You can use this method to perform some user interface changes at this moment.
     *
     * @param {PlayerTurnArgs} args
     * @param {boolean} isCurrentPlayerActive
     */
    onLeavingState(args, isCurrentPlayerActive) {
        this.game.clearHighlights();
    }

    /**
     * This method is called each time the current player becomes active or inactive in a MULTIPLE_ACTIVE_PLAYER state. You can use this method to perform some user interface changes at this moment.
     * on MULTIPLE_ACTIVE_PLAYER states, you may want to call this function in onEnteringState using `this.onPlayerActivationChange(args, isCurrentPlayerActive)` at the end of onEnteringState.
     * If your state is not a MULTIPLE_ACTIVE_PLAYER one, you can delete this function.
     *
     * @param {PlayerTurnArgs} args
     * @param {boolean} isCurrentPlayerActive
     */
    onPlayerActivationChange(args, isCurrentPlayerActive) {
    }
}

export class Game {
    /**
     * @param {Bga} bga
     */
    constructor(bga) {
        console.log('krestiki constructor');
        this.bga = bga;

        // Declare the State classes
        this.bga.states.register('PlayerTurn', new PlayerTurn(this, bga));

        // Uncomment the next line to show debug informations about state changes in the console. Remove before going to production!
        // this.bga.states.logger = console.log;
    }

    /*
        setup:

        This method must set up the game user interface according to current game situation specified
        in parameters.

        The method is called each time the game interface is displayed to a player, ie:
        _ when the game starts
        _ when a player refreshes the game page (F5)

        "gamedatas" argument contains all datas retrieved by your "getAllDatas" PHP method.
    */

    /**
     * @param {Gamedatas} gamedatas
     */
    setup(gamedatas) {
        console.log('Starting game setup', gamedatas);
        this.gamedatas = gamedatas;

        this.bga.gameArea.getElement().insertAdjacentHTML('beforeend', '<div id="krestiki-board"></div>');
        const board = document.getElementById('krestiki-board');
        for (let cell = 0; cell < gamedatas.board.length; cell++) {
            board.insertAdjacentHTML('beforeend', `<div class="krestiki-cell" id="krestiki-cell-${cell}" data-cell="${cell}"></div>`);
        }
        this.renderBoard();

        // One click funnel for the whole board (see references/tutorial-ready.md)
        board.addEventListener('click', (e) => this.onBoardClick(e));

        // Symbol badge on each player panel (X plays first)
        Object.values(gamedatas.players).forEach(player => {
            this.bga.playerPanels.getElement(Number(player.id)).insertAdjacentHTML('beforeend', `
                <div class="krestiki-symbol-badge">${SYMBOL_GLYPHS[gamedatas.symbols[player.id]] ?? ''}</div>
            `);
        });

        // Setup game notifications to handle (see "setupNotifications" method below)
        this.setupNotifications();

        console.log('Ending game setup');
    }

    ///////////////////////////////////////////////////
    //// Utility methods

    renderBoard() {
        this.gamedatas.board.forEach((owner, cell) => {
            this.renderCell(cell, Number(owner));
        });
        this.clearHighlights();
    }

    renderCell(cell, owner) {
        const el = document.getElementById(`krestiki-cell-${cell}`);
        if (!el) {
            return;
        }
        el.dataset.owner = owner;
        if (owner === 0) {
            el.innerHTML = '';
            return;
        }
        const color = this.gamedatas.players[owner]?.color ?? 'ffffff';
        const symbol = this.gamedatas.symbols[owner] ?? 'X';
        el.innerHTML = `<span class="krestiki-mark" style="color:#${color}">${SYMBOL_GLYPHS[symbol]}</span>`;
    }

    highlightPlayable(cells) {
        cells.forEach(cell => document.getElementById(`krestiki-cell-${cell}`)?.classList.add('krestiki-playable'));
    }

    clearHighlights() {
        document.querySelectorAll('.krestiki-playable').forEach(el => el.classList.remove('krestiki-playable'));
    }

    ///////////////////////////////////////////////////
    //// Reaction to cometD notifications

    /*
        setupNotifications:

        In this method, you associate each of your game notifications with your local method to handle it.

        Note: game notification names correspond to "bga->notify->all" calls in your Game.php file.

    */
    setupNotifications() {
        console.log('notifications subscriptions setup');

        // automatically listen to the notifications, based on the `notif_xxx` function on this class.
        // Uncomment the logger param to see debug information in the console about notifications.
        this.bga.notifications.setupPromiseNotifications({
            // logger: console.log
        });
    }

    async notif_piecePlaced(args) {
        const cell = parseInt(args.cell, 10);
        const owner = parseInt(args.player_id, 10);
        this.gamedatas.board[cell] = owner;
        this.renderCell(cell, owner);
        this.clearHighlights();
    }

    ///////////////////////////////////////////////////
    //// User actions

    /*
        Here, you are defining the action to trigger when the player clicks on a cell.
        This action is only triggered from a user event, never from a notification.
    */
    onBoardClick(e) {
        const cellEl = e.target.closest('.krestiki-cell');
        if (!cellEl || !cellEl.classList.contains('krestiki-playable')) {
            return;
        }
        this.bga.actions.performAction('actPlace', {
            cell: parseInt(cellEl.dataset.cell, 10),
        });
    }
}
