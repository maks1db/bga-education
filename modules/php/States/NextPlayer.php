<?php

declare(strict_types=1);

namespace Bga\Games\Krestiki\States;

use Bga\GameFramework\StateType;
use Bga\GameFramework\States\GameState;
use Bga\Games\Krestiki\Game;

class NextPlayer extends GameState
{
    public function __construct(
        protected Game $game,
    ) {
        parent::__construct($game,
            id: 90,
            type: StateType::GAME,
            updateGameProgression: true,
        );
    }

    /**
     * Game state action.
     *
     * The onEnteringState method of state `NextPlayer` is called everytime the current game state is set to `NextPlayer`.
     */
    public function onEnteringState(int $activePlayerId)
    {
        $this->game->giveExtraTime($activePlayerId);

        if ($this->game->isBoardFull()) {
            $this->game->endGameWithDraw();
            return EndScore::class;
        }

        $this->game->activeNextPlayer();

        return PlayerTurn::class;
    }
}
