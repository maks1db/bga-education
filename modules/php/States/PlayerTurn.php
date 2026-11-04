<?php

declare(strict_types=1);

namespace Bga\Games\Krestiki\States;

use Bga\GameFramework\StateType;
use Bga\GameFramework\States\GameState;
use Bga\GameFramework\States\PossibleAction;
use Bga\GameFramework\UserException;
use Bga\Games\Krestiki\Game;

class PlayerTurn extends GameState
{
    public function __construct(
        protected Game $game,
    ) {
        parent::__construct($game,
            id: 10,
            type: StateType::ACTIVE_PLAYER,
        );
    }

    /**
     * Game state arguments.
     *
     * This method returns some additional information that is very specific to the `PlayerTurn` game state.
     */
    public function getArgs(): array
    {
        return [
            'playableCells' => $this->game->getPlayableCells(),
        ];
    }

    /**
     * Player action: place a piece on a free cell.
     *
     * This method is called directly by the action trigger on the front side with `bgaPerformAction`.
     *
     * @throws UserException
     */
    #[PossibleAction]
    public function actPlace(int $cell, int $activePlayerId, array $args): string
    {
        if (!in_array($cell, $args['playableCells'], true)) {
            throw new UserException(clienttranslate('This cell is not playable'));
        }

        $board = $this->game->getBoard();
        $board[$cell] = $activePlayerId;
        $this->globals->set('board', $board);

        $this->playerStats->inc('turns_played', 1, $activePlayerId);
        $this->tableStats->inc('moves', 1);

        $this->notify->all('piecePlaced', clienttranslate('${player_name} places ${symbol}'), [
            'player_id' => $activePlayerId,
            'player_name' => $this->game->getPlayerNameById($activePlayerId),
            'cell' => $cell,
            'symbol' => $this->game->getPlayerSymbol($activePlayerId),
        ]);

        $winner = $this->game->getWinner($board);
        if ($winner !== null) {
            $this->game->endGameWithWinner($winner);
            return EndScore::class;
        }

        if ($this->game->isBoardFull($board)) {
            $this->game->endGameWithDraw();
            return EndScore::class;
        }

        return NextPlayer::class;
    }

    /**
     * This method is called each time it is the turn of a player who has quit the game (= "zombie" player).
     * You can do whatever you want in order to make sure the turn of this player ends appropriately
     * (ex: play a random card).
     *
     * See more about Zombie Mode: https://en.doc.boardgamearena.com/Zombie_Mode
     *
     * Important: your zombie code will be called when the player leaves the game. This action is triggered
     * from the main site and propagated to the gameserver from a server, not from a browser.
     * As a consequence, there is no current player associated to this action. In your zombieTurn function,
     * you must _never_ use `getCurrentPlayerId()` or `getCurrentPlayerName()`,
     * but use the $playerId passed in parameter and $this->game->getPlayerNameById($playerId) instead.
     */
    public function zombie(int $playerId): string
    {
        $args = $this->getArgs();
        $zombieChoice = $this->getRandomZombieChoice($args['playableCells']);

        return $this->actPlace($zombieChoice, $playerId, $args);
    }
}
