/**
 *------
 * BGA framework: Gregory Isabelli & Emmanuel Colin & BoardGameArena
 * reversitestA implementation : © <Your name here> <Your email address here>
 *
 * This code has been produced on the BGA studio platform for use on http://boardgamearena.com.
 * See http://en.boardgamearena.com/#!doc/Studio for more information.
 * -----
 *
 * reversitesta.js
 *
 * reversitestA user interface script
 * 
 * In this file, you are describing the logic of your user interface, in Javascript language.
 *
 */

define([
    "dojo","dojo/_base/declare",
    "ebg/core/gamegui",
    "ebg/counter"
],
function (dojo, declare) {
    return declare("bgagame.reversitesta", ebg.core.gamegui, {
        constructor: function(){
            console.log('reversitesta constructor');
              
            // Here, you can init the global variables of your user interface
            // Example:
            // this.myGlobalValue = 0;

        },
        
        /*
            setup:
            
            This method must set up the game user interface according to current game situation specified
            in parameters.
            
            The method is called each time the game interface is displayed to a player, ie:
            _ when the game starts
            _ when a player refreshes the game page (F5)
            
            "gamedatas" argument contains all datas retrieved by your "getAllDatas" PHP method.
        */
        
        setup: function(gamedatas)
        {
            console.log("Starting game setup");

            // Example to add a div on the game area
            this.getGameAreaElement().insertAdjacentHTML('beforeend', `
                <div id="board">
                    <div id="discs"></div>
                </div>
            `);
            
            // Setting up player boards
            Object.values(gamedatas.players).forEach(player => {
                // example of setting up players boards
                this.getPlayerPanelElement(player.id).insertAdjacentHTML('beforeend', `
                    <div id="player-counter-${player.id}">A player counter</div>
                `);

                // example of adding a div for each player
                // document.getElementById('player-tables').insertAdjacentHTML('beforeend', `
                //     <div id="player-table-${player.id}">
                //         <strong>${player.name}</strong>
                //         <div>Player zone content goes here</div>
                //     </div>
                // `);
            });
            
            const board = document.getElementById('board');
            const hor_scale = 64.8;
            const ver_scale = 64.4;
            for (let x = 1; x <= 8; x++) {
                for (let y = 1; y <= 8; y++) {
                    const left = Math.round((x - 1) * hor_scale + 10);
                    const top = Math.round((y - 1) * ver_scale + 7);
                    // we use afterbegin to make sure squares are placed before discs
                    board.insertAdjacentHTML(
                        `afterbegin`,
                        `<div id="square_${x}_${y}" class="square" style="left: ${left}px; top: ${top}px;"></div>`
                    );
                }
            }
 
            // square click handlers
            document.querySelectorAll('.square').forEach(
                square => square.addEventListener('click', e => this.onPlayDisc(e))
            );

            // gamedatas populated via getAllDatas()
            for (const [row, colValues] of Object.entries(gamedatas.board)) {
                for (const col in colValues) {
                    const square = gamedatas.board[row][col];
                    if (!!square) {
                        this.addDiscOnBoard(row, col, square);
                    }
                }
            }

            // Setup game notifications to handle (see "setupNotifications" method below)
            this.setupNotifications();
            console.log( "Ending game setup" );
        },
       

        ///////////////////////////////////////////////////
        //// Game & client states
        
        // onEnteringState: this method is called each time we are entering into a new game state.
        //                  You can use this method to perform some user interface changes at this moment.
        //
        onEnteringState: function(stateName, args)
        {
            console.log('Entering state: ' + stateName, args);
            
            const currentPlayerIsActive = this.isCurrentPlayerActive();

            switch( stateName ) {
                case 'playerTurn':
                    if (currentPlayerIsActive) {
                        this.updatePossibleMoves( args.args.possibleMoves );
                    }
                    break;
            }
        },

        // onLeavingState: this method is called each time we are leaving a game state.
        //                 You can use this method to perform some user interface changes at this moment.
        //
        onLeavingState: function( stateName )
        {
            console.log( 'Leaving state: '+stateName );
            
            switch( stateName )
            {
            
            /* Example:
            
            case 'myGameState':
            
                // Hide the HTML block we are displaying only during this game state
                dojo.style( 'my_html_block_id', 'display', 'none' );
                
                break;
           */
           
           
            case 'dummy':
                break;
            }               
        }, 

        // onUpdateActionButtons: in this method you can manage "action buttons" that are displayed in the
        //                        action status bar (ie: the HTML links in the status bar).
        //        
        onUpdateActionButtons: function(stateName, args)
        {
            console.log( 'onUpdateActionButtons: ' + stateName, args );
                      
            if( this.isCurrentPlayerActive() )
            {            
                switch( stateName )
                {
                 case 'playerTurn':    
                    // const playableCardsIds = args.playableCardsIds; // returned by the argPlayerTurn

                    // // Add test action buttons in the action status bar, simulating a card click:
                    // playableCardsIds.forEach(
                    //     cardId => this.statusBar.addActionButton(
                    //         _('Play card with id ${card_id}').replace('${card_id}', cardId),
                    //         () => this.onCardClick(cardId)
                    //     )
                    // );

                    // this.statusBar.addActionButton(_('Pass'), () => this.bgaPerformAction("actPass"), { color: 'secondary' });
                    break;
                }
            }
        },        

        ///////////////////////////////////////////////////
        //// Utility methods
        
        addDiscOnBoard: async function(x, y, player) {
            const color = this.gamedatas.players[player].color;

            document.getElementById('discs').insertAdjacentHTML(
                'beforeend',
                `<div class="disc" data-color="${color}" id="disc_${x}${y}"></div>`
            );

            this.placeOnObject(`disc_${x}${y}`, 'overall_player_board_' + player);

            const anim = this.slideToObject(`disc_${x}${y}`, 'square_' + x + '_' + y );
            await this.bgaPlayDojoAnimation(anim);
        },

        updatePossibleMoves: function(possibleMoves) {
            // Remove current possible moves
            document.querySelectorAll('.possibleMove').forEach(div => div.classList.remove('possibleMove'));

            for (var x in possibleMoves) {
                for (var y in possibleMoves[x]) {
                    // x,y is a possible move
                    document.getElementById(`square_${x}_${y}`).classList.add('possibleMove');
                }
            }

            this.addTooltipToClass('possibleMove', '', _('Place a disc here'));
        },


        ///////////////////////////////////////////////////
        //// Player's action
        
        /*
        
            Here, you are defining methods to handle player's action (ex: results of mouse click on 
            game objects).
            
            Most of the time, these methods:
            _ check the action is possible at this game state.
            _ make a call to the game server
        
        */
        
        // Example:
        
        // onCardClick: function( card_id )
        // {
        //     console.log( 'onCardClick', card_id );

        //     this.bgaPerformAction("actPlayCard", {
        //         card_id,
        //     }).then(() =>  {
        //         // What to do after the server call if it succeeded
        //         // (most of the time, nothing, as the game will react to notifs / change of state instead)
        //     });
        // },
        onPlayDisc: function(evt) {
            // Stop this event propagation
            evt.preventDefault();
            evt.stopPropagation();

            // Get the clicked square x and y
            // Note: square id format is "square_X_Y"
            var coords = evt.currentTarget.id.split('_');
            var x = coords[1];
            var y = coords[2];

            if (!document.getElementById(`square_${x}_${y}`).classList.contains('possibleMove')) {
                // This is not a possible move => the click does nothing
                return;
            }

            this.bgaPerformAction('actPlayDisc', {
                x : x,
                y : y
            });
        },


        
        ///////////////////////////////////////////////////
        //// Reaction to cometD notifications

        /*
            setupNotifications:
            
            In this method, you associate each of your game notifications with your local method to handle it.
            
            Note: game notification names correspond to "notifyAllPlayers" and "notifyPlayer" calls in
                  your reversitesta.game.php file.
        
        */
        setupNotifications: function()
        {
            console.log( 'notifications subscriptions setup' );
            
            // TODO: here, associate your game notifications with local methods
            
            // Example 1: standard notification handling
            // dojo.subscribe( 'cardPlayed', this, "notif_cardPlayed" );
            
            // Example 2: standard notification handling + tell the user interface to wait
            //            during 3 seconds after calling the method in order to let the players
            //            see what is happening in the game.
            // dojo.subscribe( 'cardPlayed', this, "notif_cardPlayed" );
            // this.notifqueue.setSynchronous( 'cardPlayed', 3000 );
            // 
        },  
        
        // TODO: from this point and below, you can write your game notifications handling methods
        
        /*
        Example:
        
        notif_cardPlayed: function( notif )
        {
            console.log( 'notif_cardPlayed' );
            console.log( notif );
            
            // Note: notif.args contains the arguments specified during you "notifyAllPlayers" / "notifyPlayer" PHP call
            
            // TODO: play the card in the user interface.
        },    
        
        */
   });             
});
