<?php
/**
 *------
 * BGA framework: Gregory Isabelli & Emmanuel Colin & BoardGameArena
 * reversitestA implementation : © <Your name here> <Your email address here>
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

namespace Bga\Games\reversitestA;

require_once(APP_GAMEMODULE_PATH . "module/table/table.game.php");

class Game extends \Table
{
    // private static array $CARD_TYPES;

    /**
     * Your global variables labels:
     *
     * Here, you can assign labels to global variables you are using for this game. You can use any number of global
     * variables with IDs between 10 and 99. If your game has options (variants), you also have to associate here a
     * label to the corresponding ID in `gameoptions.inc.php`.
     *
     * NOTE: afterward, you can get/set the global variables with `getGameStateValue`, `setGameStateInitialValue` or
     * `setGameStateValue` functions.
     */
    public function __construct()
    {
        parent::__construct();

        $this->initGameStateLabels([
            "my_first_global_variable" => 10,
            "my_second_global_variable" => 11,
            "my_first_game_variant" => 100,
            "my_second_game_variant" => 101,
        ]);        

        // self::$CARD_TYPES = [
        //     1 => [
        //         "card_name" => clienttranslate('Troll'), // ...
        //     ],
        //     2 => [
        //         "card_name" => clienttranslate('Goblin'), // ...
        //     ],
        //     // ...
        // ];

        /* example of notification decorator.
        // automatically complete notification args when needed
        $this->notify->addDecorator(function(string $message, array $args) {
            if (isset($args['player_id']) && !isset($args['player_name']) && str_contains($message, '${player_name}')) {
                $args['player_name'] = $this->getPlayerNameById($args['player_id']);
            }
        
            if (isset($args['card_id']) && !isset($args['card_name']) && str_contains($message, '${card_name}')) {
                $args['card_name'] = self::$CARD_TYPES[$args['card_id']]['card_name'];
                $args['i18n'][] = ['card_name'];
            }
            
            return $args;
        });*/
    }

    /**
     * This method is called only once, when a new game is launched. In this method, you must setup the game
     *  according to the game rules, so that the game is ready to be played.
     */
    protected function setupNewGame($players, $options = []) {
        // Set the colors of the players with HTML color code. The default below is red/green/blue/orange/brown. The
        // number of colors defined here must correspond to the maximum number of players allowed for the gams.
        $gameinfos = $this->getGameinfos();
        $default_colors = array('ffffff', '000000');

        $keys = array_keys($players);
        shuffle($keys);
        foreach ($keys as $player_id) {
            $player = $players[$player_id];
            $query_values[] = vsprintf("('%s', '%s', '%s', '%s', '%s')", [
                $player_id,
                array_shift($default_colors),
                $player["player_canal"],
                addslashes($player["player_name"]),
                addslashes($player["player_avatar"]),
            ]);
        }

        // Create players based on generic information.
        //
        // NOTE: You can add extra field on player table in the database (see dbmodel.sql) and initialize
        // additional fields directly here.
        static::DbQuery(
            sprintf(
                "INSERT INTO player (player_id, player_color, player_canal, player_name, player_avatar) VALUES %s",
                implode(",", $query_values)
            )
        );

        // $this->reattributeColorsBasedOnPreferences($players, $gameinfos["player_colors"]);
        $this->reloadPlayersBasicInfos();

        // Init global values with their initial values.

        // Dummy content.
        // $this->setGameStateInitialValue("my_first_global_variable", 0);

        // Init the board
        $sql = "INSERT INTO board (board_x,board_y,board_player) VALUES ";
        $sql_values = array();
        list($blackplayer_id, $whiteplayer_id) = array_keys($players);
        for ($x = 1; $x <= 8; $x++) {
            for ($y = 1; $y <= 8; $y++) {
                $token_value = "NULL";
                if (($x == 4 && $y == 4) || ($x == 5 && $y == 5)) {  // Initial positions of white player
                    $token_value = "'$whiteplayer_id'";
                } else if (($x == 4 && $y == 5) || ($x == 5 && $y == 4)) {  // Initial positions of black player
                    $token_value = "'$blackplayer_id'";
                }

                $sql_values[] = "('$x','$y',$token_value)";
            }
        }
        $sql .= implode(',', $sql_values);
        $this->DbQuery($sql);


        // Activate first player once everything has been initialized and ready.
        $this->gamestate->changeActivePlayer($blackplayer_id);
    }

    /**
     * Player action, example content.
     *
     * In this scenario, each time a player plays a card, this method will be called. This method is called directly
     * by the action trigger on the front side with `bgaPerformAction`.
     *
     * @throws BgaUserException
     */
    function actPlayDisc(int $x, int $y) {
        $playerID = intval($this->getActivePlayerId());

        // Now, check if this is a possible move
        $board = $this->getBoardPieces();
        $turnedOverDiscs = $this->getTurnedOverDiscs($board, $x, $y, $playerID);

        if (count($turnedOverDiscs) > 0) {
            // valid move
            // Let's place a disc at x,y and return all "$returned" discs to the active player
            $sql = "UPDATE board SET board_player='$playerID'
                    WHERE ( board_x, board_y) IN ( ";

            foreach( $turnedOverDiscs as $turnedOver ) {
                $sql .= "('".$turnedOver['x']."','".$turnedOver['y']."'),";
            }
            $sql .= "('$x','$y') ) ";

            $this->DbQuery($sql);

            // Update scores according to the number of disc on board
            $scoreSql = "UPDATE player
                    SET player_score = (
                    SELECT COUNT( board_x ) FROM board WHERE board_player=player_id
                    )";
            $this->DbQuery($scoreSql);

            // Statistics
            $this->incStat(count($turnedOverDiscs), "turnedOver", $playerID);
            if (($x==1 && $y==1) || ($x==8 && $y==1) || ($x==1 && $y==8) || ($x==8 && $y==8) ) {
                $this->incStat(1, 'discPlayedOnCorner', $playerID);
            } else if ($x==1 || $x==8 || $y==1 || $y==8) {
                $this->incStat(1, 'discPlayedOnBorder', $playerID);
            } else if ($x>=3 && $x<=6 && $y>=3 && $y<=6) {
                $this->incStat(1, 'discPlayedOnCenter', $playerID);
            }

            // Notify
            $this->notify->all("playDisc", clienttranslate( '${player_name} plays a disc and turns over ${returned_nbr} disc(s)' ), array(
                'player_id' => $playerID,
                'player_name' => $this->getActivePlayerName(),
                'returned_nbr' => count($turnedOverDiscs),
                'x' => $x,
                'y' => $y
            ));

            $this->notify->all("turnOverDiscs", '', array(
                'player_id' => $playerID,
                'turnedOver' => $turnedOverDiscs
            ));

            $newScores = $this->getCollectionFromDb( "SELECT player_id, player_score FROM player", true );
            $this->notify->all("newScores", "", array(
                "scores" => $newScores
            ));

            // Then, go to the next state
            $this->gamestate->nextState('nextPlayer');
        } else {
            throw new \BgaSystemException("Impossible move");
        }
    }

    // public function actPass(): void
    // {
    //     // Retrieve the active player ID.
    //     $player_id = (int)$this->getActivePlayerId();

    //     // Notify all players about the choice to pass.
    //     $this->notify->all("pass", clienttranslate('${player_name} passes'), [
    //         "player_id" => $player_id,
    //         "player_name" => $this->getActivePlayerName(), // remove this line if you uncomment notification decorator
    //     ]);

    //     // at the end of the action, move to the next state
    //     $this->gamestate->nextState("pass");
    // }

    public function getBoardPieces() {
        $pieces = self::getObjectListFromDB(
            "SELECT board_x x, board_y y, board_player player
            FROM board
            WHERE board_player IS NOT NULL"
        );

        $board = array();
        foreach ($pieces as $i => $p) {
            if (!array_key_exists($p['x'], $board)) {
                $board[$p['x']] = array();
            }
            $board[$p['x']][$p['y']] = $p['player'];
        }
        return $board;
    }

    // return array of disc coords. that would be turned over if a new disc placed at (x, y) location for given player
    public function getTurnedOverDiscs($board, $x, $y, $playerID): array {
        $MOVE_DIRECTIONS = [
            [-1, 0],
            [-1, 1],
            [0, 1],
            [1, 1],
            [1, 0],
            [1, -1],
            [0, -1],
            [-1, -1]
        ];

        // does this space have a disc already?
        if (
            array_key_exists($x, $board) &&
            array_key_exists($y, $board[$x]) &&
            $board[$x][$y]
        ) {
            return [];
        }

        $results = [];

        // count the number of opposing discs between this disc and the next allied disc (check all 8 directions)
        foreach ($MOVE_DIRECTIONS as $vector) {
            $xDelta = $vector[0];
            $yDelta = $vector[1];

            $flipped = [];
            $foundAllied = false;

            if ($yDelta == 0) {
                // single loop for $yDelta=0 edge case
                for ($i = $x + $xDelta; 1 <= $i && $i <= 8; $i += $xDelta) {
                    // try
                    $playerIDToCheck = (
                        array_key_exists($i, $board) && array_key_exists($y, $board[$i])
                            ? $board[$i][$y]
                            : null
                    );
                    if ($playerIDToCheck) {
                        if ($playerIDToCheck == $playerID) {
                            $foundAllied = true;
                            break;
                        } else {
                            $flipped[] = ["x" => $i, "y" => $y];
                        }
                    } else {
                        // no piece on this square - invalid move
                        break;
                    }
                    // catch
                }
            } else if ($xDelta == 0) {
                for ($j = $y + $yDelta; 1 <= $j && $j <= 8; $j += $yDelta) {
                    // try
                    $playerIDToCheck = (
                        array_key_exists($x, $board) && array_key_exists($j, $board[$x])
                            ? $board[$x][$j]
                            : null
                    );
                    if ($playerIDToCheck) {
                        if ($playerIDToCheck == $playerID) {
                            $foundAllied = true;
                            break;
                        } else {
                            $flipped[] = ["x" => $x, "y" => $j];
                        }
                    } else {
                        // no piece on this square - invalid move
                        break;
                    }
                    // catch
                }
            } else {
                $i = $x + $xDelta;
                $j = $y + $yDelta;
                do {
                    // try
                    $playerIDToCheck = (
                        array_key_exists($i, $board) && array_key_exists($j, $board[$i])
                            ? $board[$i][$j]
                            : null
                    );
                    if ($playerIDToCheck) {
                        if ($playerIDToCheck == $playerID) {
                            $foundAllied = true;
                            break;
                        } else {
                            $flipped[] = ["x" => $i, "y" => $j];
                        }
                    } else {
                        // no piece on this square - invalid move
                        break;
                    }
                    // catch

                    $i += $xDelta;
                    $j += $yDelta;
                } while ((1 <= $i && $i <= 8 && 1 <= $j && $j <= 8) && !$foundAllied);
            }

            if ($foundAllied && count($flipped) > 0) {
                $results = array_merge($results, $flipped);
            }
        }

        return $results;
    }

    /*
        [
            x1 => [
                y1 => flipCount1,
                y2 => flipCount2
            ],
            x2 => [
                y1 => flipCount4,
                y2 => flipCount5
            ],
            x3 => [
                y3 => flipCount3
            ],
            ...
        ]
    */
    public function getPossibleMoves($playerID): array {
        $MOVE_DIRECTIONS = [
            [-1, 0],
            [-1, 1],
            [0, 1],
            [1, 1],
            [1, 0],
            [1, -1],
            [0, -1],
            [-1, -1]
        ];
        $resultMoves = [];

        $board = $this->getBoardPieces();

        for ($x = 1; $x < 9; $x++) {
            for ($y = 1; $y < 9; $y++) {
                $flippedDiscs = $this->getTurnedOverDiscs($board, $x, $y, $playerID);
                if (count($flippedDiscs) > 0) {
                    if (!array_key_exists($x, $resultMoves)) {
                        $resultMoves[$x] = array();
                    }
                    // add possible move for specified player to result-moves set
                    $resultMoves[$x][$y] = $flippedDiscs;
                }
            }
        }

        return $resultMoves;
    }

    /**
     * Game state arguments, example content.
     *
     * This method returns some additional information that is very specific to the `playerTurn` game state.
     *
     * @return array
     * @see ./states.inc.php
     */
    public function argPlayerTurn(): array {
        // Get some values from the current game situation from the database.
        return [
            'possibleMoves' => $this->getPossibleMoves( intval($this->getActivePlayerId()) )
        ];
    }

    /**
     * Compute and return the current game progression.
     *
     * The number returned must be an integer between 0 and 100.
     *
     * This method is called each time we are in a game state with the "updateGameProgression" property set to true.
     *
     * @return int
     * @see ./states.inc.php
     */
    public function getGameProgression()
    {
        return 0;
    }

    /**
     * Game state action, example content.
     *
     * The action method of state `nextPlayer` is called everytime the current game state is set to `nextPlayer`.
     */
    function stNextPlayer(): void {
        // Make next player in the order the active player
        $next_player_id = intval($this->activeNextPlayer());

        $this->debug("[stNextPlayer] entered nextPlayer state");
        $this->dump('new active player ID: ', $next_player_id);

        // Check if both player has at least 1 discs, and if there are free squares to play
        $player_to_discs = $this->getCollectionFromDb( "SELECT board_player, COUNT( board_x )
                                                       FROM board
                                                       GROUP BY board_player", true );

        if (!isset($player_to_discs[''])) {
            // empty-string index not present => there's no more free place on the board !
            // => end of the game
            $this->gamestate->nextState('endGame');
            return;
        } else if (!isset($player_to_discs[$next_player_id])) {
            // Active player has no more disc on the board => he loses immediately
            $this->gamestate->nextState('endGame');
            return;
        }

        // Can this player play?
        $possibleMoves = $this->getPossibleMoves($next_player_id);

        if (count($possibleMoves) == 0) {
            // This player can't play
            // Can his opponent play ?
            $opponent_id = (int)$this->getUniqueValueFromDb("SELECT player_id FROM player WHERE player_id != '$next_player_id' ");
            $opponentPossibleMoves = $this->getPossibleMoves($opponent_id);
            // var_dump('$opponentPossibleMoves: ', $opponentPossibleMoves);
            if (count($opponentPossibleMoves) == 0) {
                // Nobody can move => end of the game
                $this->gamestate->nextState('endGame');
            } else {
                // => pass his turn
                $next_player_id = intval($this->activeNextPlayer());
                $this->gamestate->nextState('playerTurn');
            }
        } else {
            // This player can play. Give him some extra time
            $this->giveExtraTime($next_player_id);
            $this->gamestate->nextState('playerTurn');
        }
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
    public function upgradeTableDb($from_version) {
        //       if ($from_version <= 1404301345)
        //       {
        //            // ! important ! Use DBPREFIX_<table_name> for all tables
        //
        //            $sql = "ALTER TABLE DBPREFIX_xxxxxxx ....";
        //            $this->applyDbUpgradeToAllDB( $sql );
        //       }
        //
        //       if ($from_version <= 1405061421)
        //       {
        //            // ! important ! Use DBPREFIX_<table_name> for all tables
        //
        //            $sql = "CREATE TABLE DBPREFIX_xxxxxxx ....";
        //            $this->applyDbUpgradeToAllDB( $sql );
        //       }
    }

    /*
     * Gather all information about current game situation (visible by the current player).
     *
     * The method is called each time the game interface is displayed to a player, i.e.:
     *
     * - when the game starts
     * - when a player refreshes the game page (F5)
     */
    protected function getAllDatas(): array
    {
        $result = [];

        // WARNING: We must only return information visible by the current player.
        $current_player_id = (int) $this->getCurrentPlayerId();

        // Get information about players.
        // NOTE: you can retrieve some extra field you added for "player" table in `dbmodel.sql` if you need it.
        $result["players"] = $this->getCollectionFromDb(
            "SELECT `player_id` `id`, `player_score` `score`, `player_color` `color` FROM `player`"
        );


        $result['board'] = $this->getBoardPieces();

        return $result;
    }

    /**
     * Returns the game name.
     *
     * IMPORTANT: Please do not modify.
     */
    protected function getGameName()
    {
        return "reversitesta";
    }

    /**
     * This method is called each time it is the turn of a player who has quit the game (= "zombie" player).
     * You can do whatever you want in order to make sure the turn of this player ends appropriately
     * (ex: pass).
     *
     * Important: your zombie code will be called when the player leaves the game. This action is triggered
     * from the main site and propagated to the gameserver from a server, not from a browser.
     * As a consequence, there is no current player associated to this action. In your zombieTurn function,
     * you must _never_ use `getCurrentPlayerId()` or `getCurrentPlayerName()`, otherwise it will fail with a
     * "Not logged" error message.
     *
     * @param array{ type: string, name: string } $state
     * @param int $active_player
     * @return void
     * @throws feException if the zombie mode is not supported at this game state.
     */
    protected function zombieTurn(array $state, int $active_player): void
    {
        $state_name = $state["name"];

        if ($state["type"] === "activeplayer") {
            switch ($state_name) {
                default:
                {
                    $this->gamestate->nextState("zombiePass");
                    break;
                }
            }

            return;
        }

        // Make sure player is in a non-blocking status for role turn.
        if ($state["type"] === "multipleactiveplayer") {
            $this->gamestate->setPlayerNonMultiactive($active_player, '');
            return;
        }

        throw new \feException("Zombie mode not supported at this game state: \"{$state_name}\".");
    }
}
