<?php

declare(strict_types=1);

namespace Bga\Games\Krestiki\States;

use Bga\GameFramework\StateType;
use Bga\GameFramework\States\GameState;
use Bga\Games\Krestiki\Game;

const ST_END_GAME = 99;

class EndScore extends GameState
{
    public function __construct(
        protected Game $game,
    ) {
        parent::__construct($game,
            id: 98,
            type: StateType::GAME,
        );
    }

    /**
     * Game state action.
     *
     * The onEnteringState method of state `EndScore` is called just before the end of the game.
     * Scores are already set by the action that ended the game (see Game::endGameWithWinner / endGameWithDraw).
     */
    public function onEnteringState()
    {
        return ST_END_GAME;
    }
}
