<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Adventure</title>
    <style>
        body { font-family: Arial, Helvetica, sans-serif; padding: 20px; }
        .btn { padding: 8px 12px; margin: 4px; cursor: pointer; }
        .box { border: 1px solid #ddd; padding: 12px; margin-bottom: 12px; }
    </style>
</head>
<body>
    <h1>Adventure</h1>

    <div class="box">
        <div><strong>Player HP:</strong> <span id="playerHp">-</span></div>
        <div><strong>Monster:</strong> <span id="monsterName">-</span></div>
        <div><strong>Monster HP:</strong> <span id="monsterHp">-</span></div>
        <div id="message" style="margin-top:8px;color:#333"></div>
    </div>

    <div id="map" class="box" style="margin-bottom:12px">
        <h3>Map & Encounters</h3>
        <div>
            <button class="btn start" data-monster="slime">Go fight Slime (easy)</button>
            <button class="btn start" data-monster="goblin">Go fight Goblin (medium)</button>
            <button class="btn start" data-monster="orc">Go fight Orc (hard)</button>
        </div>
    </div>

    <div>
        <button id="attackBtn" class="btn">Attack</button>
        <button id="useSmall" class="btn">Use Small Potion</button>
        <button id="useLarge" class="btn">Use Large Potion</button>
        <button id="runBtn" class="btn">Run</button>
        <a href="/game" style="margin-left:12px">Back to Candybag</a>
    </div>

    <script>
        const adv = {!! $adventure !!};
        const state = {!! $state !!};
        const csrf = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

        function render(a) {
            const mapDiv = document.getElementById('map');
            function setNoEncounter() {
                document.getElementById('playerHp').textContent = (state.player_hp || '-') + '/' + (state.player_max_hp || '-');
                document.getElementById('monsterName').textContent = '-';
                document.getElementById('monsterHp').textContent = '-';
                document.getElementById('message').textContent = 'No active encounter.';
                document.getElementById('attackBtn').disabled = true;
                document.getElementById('runBtn').disabled = true;
                document.getElementById('useSmall').disabled = true;
                document.getElementById('useLarge').disabled = true;
                mapDiv.style.display = 'block';
            }

            function renderEncounter(a) {
                if (!a) return setNoEncounter();
                mapDiv.style.display = 'none';
                document.getElementById('playerHp').textContent = a.player_hp + '/' + a.player_max_hp;
                document.getElementById('monsterName').textContent = a.monster.name;
                document.getElementById('monsterHp').textContent = a.monster.hp + '/' + a.monster.max_hp;
                document.getElementById('message').textContent = a.message || '';
                document.getElementById('attackBtn').disabled = false;
                document.getElementById('runBtn').disabled = false;
                document.getElementById('useSmall').disabled = false;
                document.getElementById('useLarge').disabled = false;
            }
        }

        if (adv) renderEncounter(adv); else setNoEncounter();

        function postAction(type, extra) {
            const body = Object.assign({ type }, extra || {});
            fetch('/game/adventure/action', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf },
                body: JSON.stringify(body)
            }).then(r => r.json()).then(res => {
                if (res.adventure) renderEncounter(res.adventure);
                else {
                    setNoEncounter();
                    document.getElementById('message').textContent = res.state ? 'Encounter ended. Back to town.' : 'Encounter ended.';
                }
            }).catch(console.error);
        }

        document.getElementById('attackBtn').addEventListener('click', () => postAction('attack'));
        document.getElementById('runBtn').addEventListener('click', () => postAction('run'));
        document.getElementById('useSmall').addEventListener('click', () => postAction('use_potion', { which: 'small' }));
        document.getElementById('useLarge').addEventListener('click', () => postAction('use_potion', { which: 'large' }));

        // start encounter buttons
        document.querySelectorAll('.start').forEach(b => {
            b.addEventListener('click', () => {
                const monster = b.getAttribute('data-monster');
                fetch('/game/adventure/start', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf },
                    body: JSON.stringify({ monster })
                }).then(r => r.json()).then(res => {
                    if (res.adventure) renderEncounter(res.adventure);
                }).catch(console.error);
            });
        });
    </script>
</body>
</html>
