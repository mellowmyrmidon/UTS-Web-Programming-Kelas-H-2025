<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\GameState;
use App\Models\AdventureState;

class GameController extends Controller
{
    private function getSessionId(Request $request)
    {
        return session()->getId();
    }

    private function getGameState(Request $request)
    {
        $sessionId = $this->getSessionId($request);
        $record = GameState::where('session_id', $sessionId)->first();
        
        if ($record) {
            return $record->state;
        }
        
        return [
            'candies' => 0,
            'cps' => 0,
            'items' => [],
            'total_candies' => 0,
            'shop_unlocked' => false,
            'has_wooden_sword' => false,
            'player_hp' => 20,
            'player_max_hp' => 20,
            'player_attack' => 1,
            'last_tick' => time(),
        ];
    }

    private function saveGameState(Request $request, array $state)
    {
        $sessionId = $this->getSessionId($request);
        GameState::updateOrCreate(
            ['session_id' => $sessionId],
            ['state' => $state]
        );
    }

    private function getAdventureState(Request $request)
    {
        $sessionId = $this->getSessionId($request);
        $record = AdventureState::where('session_id', $sessionId)->first();
        return $record ? $record->adventure : null;
    }

    private function saveAdventureState(Request $request, ?array $adv)
    {
        $sessionId = $this->getSessionId($request);
        if ($adv === null) {
            AdventureState::where('session_id', $sessionId)->delete();
        } else {
            AdventureState::updateOrCreate(
                ['session_id' => $sessionId],
                ['adventure' => $adv]
            );
        }
    }

    // Return the game page with current state
    public function index(Request $request)
    {
        $state = $this->getGameState($request);
        $state = $this->applyTick($state);
        
        if (isset($state['total_candies']) && $state['total_candies'] >= 100) {
            $state['shop_unlocked'] = true;
        }
        // ensure player attack reflects wooden sword ownership
        if (!empty($state['has_wooden_sword'])) {
            $state['player_attack'] = 6; // wooden sword grants +5 base
        } else {
            $state['player_attack'] = 1;
        }
        
        $this->saveGameState($request, $state);

        return view('game', ['state' => json_encode($state)]);
    }

    // Handle game actions (click, buy)
    public function action(Request $request)
    {
        $state = $this->getGameState($request);
        $state = $this->applyTick($state);

        $type = $request->input('type');

        if ($type === 'click') {
            // manual click gives 1 candy
            $state['candies'] += 1;
            $state['total_candies'] = ($state['total_candies'] ?? 0) + 1;
        } elseif ($type === 'buy') {
            $item = $request->input('item');
            $catalog = [
                'cursor' => ['cost' => 15, 'cps' => 1],
                'farm' => ['cost' => 120, 'cps' => 6],
                'factory' => ['cost' => 1200, 'cps' => 60],
                'wooden_sword' => ['cost' => 75, 'cps' => 0],
                'potion_small' => ['cost' => 25, 'cps' => 0],
                'potion_large' => ['cost' => 100, 'cps' => 0],
            ];

            if (isset($catalog[$item]) && $state['candies'] >= $catalog[$item]['cost']) {
                // only allow wooden sword if shop unlocked
                if ($item === 'wooden_sword' && empty($state['shop_unlocked'])) {
                    // ignore
                } else {
                    $state['candies'] -= $catalog[$item]['cost'];
                    $state['cps'] += $catalog[$item]['cps'];
                    if (!isset($state['items'][$item])) {
                        $state['items'][$item] = 0;
                    }
                    $state['items'][$item]++;
                    if ($item === 'wooden_sword') {
                        $state['has_wooden_sword'] = true;
                        $state['player_attack'] = 6;
                    }
                }
            }
        } elseif ($type === 'save') {
            // allow client to push lightweight state (optional)
            $push = $request->input('state');
            if (is_array($push)) {
                // merge safe fields
                foreach (['candies','cps','items'] as $k) {
                    if (isset($push[$k])) {
                        $state[$k] = $push[$k];
                    }
                }
            }
        } elseif ($type === 'use_potion') {
            $which = $request->input('which');
            if ($which === 'small' && !empty($state['items']['potion_small'])) {
                $state['items']['potion_small']--;
                $state['player_hp'] = min($state['player_max_hp'], $state['player_hp'] + 10);
            } elseif ($which === 'large' && !empty($state['items']['potion_large'])) {
                $state['items']['potion_large']--;
                $state['player_hp'] = min($state['player_max_hp'], $state['player_hp'] + 30);
            }
        }

        // unlock shop when enough total candies collected
        if (isset($state['total_candies']) && $state['total_candies'] >= 100) {
            $state['shop_unlocked'] = true;
        }

        $this->saveGameState($request, $state);

        return response()->json($state);
    }

    // Advance candies based on cps and time elapsed
    private function applyTick(array $state)
    {
        $now = time();
        $last = isset($state['last_tick']) ? (int)$state['last_tick'] : $now;
        $dt = max(0, $now - $last);
        if ($dt > 0 && isset($state['cps'])) {
            $gain = $state['cps'] * $dt;
            $state['candies'] += $gain;
            $state['total_candies'] = ($state['total_candies'] ?? 0) + $gain;
        }
        $state['last_tick'] = $now;
        return $state;
    }

    // Adventure area: show a simple monster encounter page
    public function adventure(Request $request)
    {
        $state = $this->getGameState($request);
        
        // ensure shop unlocked flag is up-to-date
        if ($state['total_candies'] >= 100) {
            $state['shop_unlocked'] = true;
        }

        // do not auto-start an encounter here; show map and current player stats
        $adv = $this->getAdventureState($request);

        $this->saveGameState($request, $state);
        return view('adventure', ['adventure' => json_encode($adv), 'state' => json_encode($state)]);
    }

    // Adventure actions: attack/run
    public function adventureAction(Request $request)
    {
        $state = $this->getGameState($request);
        $adv = $this->getAdventureState($request);
        
        if (!$adv) {
            return response()->json(['error' => 'No adventure started'], 400);
        }
        
        $type = $request->input('type');

        if ($type === 'attack') {
            // player attacks
            $adv['monster']['hp'] -= $adv['player_attack'];
            $adv['message'] = "You hit the {$adv['monster']['name']} for {$adv['player_attack']} damage.";
            if ($adv['monster']['hp'] <= 0) {
                // defeated
                $reward = $adv['monster']['reward'] ?? 20;
                $adv['message'] = "You defeated the {$adv['monster']['name']}! You gain {$reward} candies.";
                $state['candies'] += $reward;
                $state['total_candies'] = ($state['total_candies'] ?? 0) + $reward;
                // clear adventure
                $this->saveAdventureState($request, null);
                $this->saveGameState($request, $state);
                return response()->json(['state' => $state, 'adventure' => null]);
            }

            // monster retaliates
            $adv['player_hp'] -= $adv['monster']['attack'];
            $adv['message'] .= " The {$adv['monster']['name']} hits you for {$adv['monster']['attack']} damage.";
            if ($adv['player_hp'] <= 0) {
                $adv['message'] = "You were defeated by the {$adv['monster']['name']}.'";
                // respawn player with half HP penalty
                $state['player_hp'] = max(1, intval($state['player_max_hp'] / 2));
                $this->saveAdventureState($request, null);
                $this->saveGameState($request, $state);
                return response()->json(['state' => $state, 'adventure' => null, 'dead' => true]);
            }
        } elseif ($type === 'run') {
            $adv['message'] = 'You fled the battle.';
            $this->saveAdventureState($request, null);
            $this->saveGameState($request, $state);
            return response()->json(['state' => $state, 'adventure' => null]);
        } elseif ($type === 'use_potion') {
            $which = $request->input('which');
            if ($which === 'small' && !empty($state['items']['potion_small'])) {
                $state['items']['potion_small']--;
                $state['player_hp'] = min($state['player_max_hp'], $state['player_hp'] + 10);
                $adv['message'] = 'You used a small potion.';
            } elseif ($which === 'large' && !empty($state['items']['potion_large'])) {
                $state['items']['potion_large']--;
                $state['player_hp'] = min($state['player_max_hp'], $state['player_hp'] + 30);
                $adv['message'] = 'You used a large potion.';
            }
            $this->saveAdventureState($request, $adv);
            $this->saveGameState($request, $state);
            return response()->json(['state' => $state, 'adventure' => $adv]);
        }

        $this->saveAdventureState($request, $adv);
        $this->saveGameState($request, $state);
        return response()->json(['state' => $state, 'adventure' => $adv]);
    }

    // Start an encounter from the map
    public function adventureStart(Request $request)
    {
        $state = $this->getGameState($request);

        $monsterKey = $request->input('monster');
        $monsters = [
            'slime' => ['name' => 'Slime', 'hp' => 20, 'max_hp' => 20, 'attack' => 2, 'reward' => 20],
            'goblin' => ['name' => 'Goblin', 'hp' => 40, 'max_hp' => 40, 'attack' => 5, 'reward' => 60],
            'orc' => ['name' => 'Orc', 'hp' => 120, 'max_hp' => 120, 'attack' => 12, 'reward' => 250],
        ];

        if (!isset($monsters[$monsterKey])) {
            return response()->json(['error' => 'Unknown monster'], 400);
        }

        $playerAttack = !empty($state['has_wooden_sword']) ? 6 : 1;

        $adv = [
            'player_hp' => $state['player_hp'],
            'player_max_hp' => $state['player_max_hp'],
            'player_attack' => $playerAttack,
            'monster' => $monsters[$monsterKey],
            'message' => "A wild {$monsters[$monsterKey]['name']} appears!",
        ];

        $this->saveAdventureState($request, $adv);
        $this->saveGameState($request, $state);
        return response()->json(['adventure' => $adv, 'state' => $state]);
    }
}
