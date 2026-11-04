<?php
/**
 *------
 * BGA framework: Gregory Isabelli & Emmanuel Colin & BoardGameArena
 * Krestiki implementation : © <Your name here> <Your email address here>
 *
 * This code has been produced on the BGA studio platform for use on http://boardgamearena.com.
 * See http://en.boardgamearena.com/#!doc/Studio for more information.
 * -----
 *
 * Game.php
 *
 * This is the main file for your game logic.
 *
 * In this PHP file, you are going to defines the rules of the game.
 */
declare(strict_types=1);

namespace Bga\Games\Krestiki;

use Bga\Games\Krestiki\States\PlayerTurn;
use Bga\GameFramework\Table;

class Game extends Table
{
    public const BOARD_CELLS = 9;

    public const WIN_LINES = [
        [0, 1, 2],
        [3, 4, 5],
        [6, 7, 8],
        [0, 3, 6],
        [1, 4, 7],
        [2, 5, 8],
        [0, 4, 8],
        [2, 4, 6],
    ];

    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Compute and return the current game progression.
     *
     * The number returned must be an integer between 0 and 100.
     *
     * This method is called each time we are in a game state with the "updateGameProgression" property set to true.
     *
     * @return int
     */
    public function getGameProgression()
    {
        $board = $this->getBoard();
        $filled = count(array_filter($board));

        return (int) round($filled / count($board) * 100);
    }

    /**
     * Migrate database.
     *
     * You don't have to care about this until your game has been published on BGA. Once your game is on BGA, this
     * method is called everytime the system detects a game running with your old database scheme. In this case, if you
     * change your database scheme, you just have to apply the needed changes in order to update the game database and
     * allow the game to continue to run with your new version.
     *
     * @param int $from_version
     * @return void
     */
    public function upgradeTableDb($from_version)
    {
    }

    /*
     * Gather all information about current game situation (visible by the current player).
     *
     * The method is called each time the game interface is displayed to a player, i.e.:
     *
     * - when the game starts
     * - when a player refreshes the game page (F5)
     */
    public function getAllDatas(int $currentPlayerId): array
    {
        return [
            'players' => $this->getCollectionFromDb(
                "SELECT `player_id` AS `id`, `player_score` AS `score`, `player_color` AS `color`, `player_name` AS `name` FROM `player`"
            ),
            'board' => $this->getBoard(),
            'symbols' => $this->getPlayerSymbols(),
        ];
    }

    /**
     * This method is called only once, when a new game is launched. In this method, you must setup the game
     *  according to the game rules, so that the game is ready to be played.
     */
    protected function setupNewGame($players, $options = [])
    {
        $gameinfos = $this->getGameinfos();
        $default_colors = $gameinfos['player_colors'];

        foreach ($players as $player_id => $player) {
            $query_values[] = vsprintf("(%s, '%s', '%s')", [
                $player_id,
                array_shift($default_colors),
                addslashes($player['player_name']),
            ]);
        }

        static::DbQuery(
            sprintf(
                "INSERT INTO `player` (`player_id`, `player_color`, `player_name`) VALUES %s",
                implode(',', $query_values)
            )
        );

        $this->reattributeColorsBasedOnPreferences($players, $gameinfos['player_colors']);
        $this->reloadPlayersBasicInfos();

        $this->bga->globals->set('board', array_fill(0, self::BOARD_CELLS, 0));
        $this->bga->globals->set('x_player_id', (int) array_key_first($players));

        $this->bga->tableStats->init('moves', 0);
        $this->bga->playerStats->init('turns_played', 0);

        $this->activeNextPlayer();

        return PlayerTurn::class;
    }

    public function getBoard(): array
    {
        $board = $this->bga->globals->get('board', array_fill(0, self::BOARD_CELLS, 0));

        return array_map('intval', $board);
    }

    public function getPlayableCells(?array $board = null): array
    {
        $board = $board ?? $this->getBoard();
        $cells = [];
        foreach ($board as $cell => $owner) {
            if ($owner === 0) {
                $cells[] = $cell;
            }
        }

        return $cells;
    }

    public function isBoardFull(?array $board = null): bool
    {
        return count($this->getPlayableCells($board)) === 0;
    }

    /**
     * Returns the player_id of the winner, or null if no winning line is complete.
     */
    public function getWinner(?array $board = null): ?int
    {
        $board = $board ?? $this->getBoard();
        foreach (self::WIN_LINES as $line) {
            [$a, $b, $c] = $line;
            if ($board[$a] !== 0 && $board[$a] === $board[$b] && $board[$b] === $board[$c]) {
                return $board[$a];
            }
        }

        return null;
    }

    public function getPlayerSymbol(int $playerId): string
    {
        return $this->getXPlayerId() === $playerId ? 'X' : 'O';
    }

    public function getPlayerSymbols(): array
    {
        $symbols = [];
        foreach ($this->getPlayerIds() as $playerId) {
            $symbols[$playerId] = $this->getPlayerSymbol($playerId);
        }

        return $symbols;
    }

    public function getXPlayerId(): int
    {
        return (int) $this->bga->globals->get('x_player_id');
    }

    /**
     * @return int[]
     */
    public function getPlayerIds(): array
    {
        return array_map('intval', array_keys($this->loadPlayersBasicInfos()));
    }

    public function getOpponentId(int $playerId): int
    {
        foreach ($this->getPlayerIds() as $id) {
            if ($id !== $playerId) {
                return $id;
            }
        }

        throw new \LogicException("No opponent found for player $playerId");
    }

    /**
     * [A3] Win = 2, loss = 0.
     */
    public function endGameWithWinner(int $winnerId): void
    {
        $this->bga->playerScore->set($winnerId, 2);
        $this->bga->playerScore->set($this->getOpponentId($winnerId), 0);
    }

    /**
     * [A3] Draw = 1 for both players.
     */
    public function endGameWithDraw(): void
    {
        foreach ($this->getPlayerIds() as $playerId) {
            $this->bga->playerScore->set($playerId, 1);
        }
    }

    /**
     * Example of debug function.
     * Here, jump to a state you want to test (by default, jump to next player state)
     * You can trigger it on Studio using the Debug button on the right of the top bar.
     */
    public function debug_goToState(int $state = 3)
    {
        if ($this->getBgaEnvironment() !== 'studio') {
            return;
        }
        $this->gamestate->jumpToState($state);
    }
}
