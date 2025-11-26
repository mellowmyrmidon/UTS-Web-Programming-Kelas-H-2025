<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Mini RPG - Candybox-like</title>
    <style>
        body { font-family: Arial, Helvetica, sans-serif; padding: 20px; }
        .big { font-size: 2em; margin-bottom: 10px; }
        .btn { padding: 8px 12px; margin: 4px; cursor: pointer; }
        .shop { margin-top: 12px; }
        .items { margin-top: 12px; }
    </style>
</head>
<body>
    <h1> Candybag </h1>
    <div class="big">Candies: <span id="candies">0</span></div>
    <div>Candies per second: <span id="cps">0</span></div>
    <div>Player HP: <span id="playerHp">-</span></div>

    <button id="clickBtn" class="btn">Click (+1)</button>

    <div id="shopDiv" class="shop">
        <h3>Shop</h3>
        <div id="shopContent">
            <button class="btn buy" data-item="cursor">Buy Cursor (cost 15, +1 cps)</button>
            <button class="btn buy" data-item="farm">Buy Farm (cost 120, +6 cps)</button>
            <button class="btn buy" data-item="factory">Buy Factory (cost 1200, +60 cps)</button>
            <div style="margin-top:8px">
                <button class="btn buy" data-item="potion_small">Buy Small Potion (cost 25, heal 10)</button>
                <button class="btn buy" data-item="potion_large">Buy Large Potion (cost 100, heal 30)</button>
            </div>
        </div>
        <div id="shopLocked" style="color:#666">Shop locked — collect 100 total candies to unlock.</div>
    </div>

    <div style="margin-top:8px">
        <button id="buySwordBtn" class="btn" style="display:none" data-item="wooden_sword">Buy Wooden Sword (cost 75)</button>
        <a id="adventureLink" href="/game/adventure" style="display:none;margin-left:12px">Go to Adventure</a>
    </div>

    <div class="items">
        <h3>Owned</h3>
        <div id="itemsList">None</div>
    </div>

    <div style="margin-top:18px;color:#666;font-size:0.9em">State is saved in the session on the server. This is a lightweight starting implementation.</div>

    <script>
        const state = {!! $state !!};
        const csrf = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

        const elCandies = document.getElementById('candies');
        const elCps = document.getElementById('cps');
        const elItems = document.getElementById('itemsList');
        const elPlayerHp = document.getElementById('playerHp');

        function render(s) {
            elCandies.textContent = Math.floor(s.candies);
            elCps.textContent = s.cps;
            const keys = Object.keys(s.items || {});
            if (keys.length === 0) elItems.textContent = 'None';
            else elItems.innerHTML = keys.map(k => `${k}: ${s.items[k]}`).join('<br>');

            elPlayerHp.textContent = (s.player_hp || '-') + '/' + (s.player_max_hp || '-');

            // shop UI
            const shopLocked = document.getElementById('shopLocked');
            const shopContent = document.getElementById('shopContent');
            const buySwordBtn = document.getElementById('buySwordBtn');
            const adventureLink = document.getElementById('adventureLink');
            if (s.shop_unlocked) {
                shopLocked.style.display = 'none';
                shopContent.style.display = 'block';
                buySwordBtn.style.display = s.has_wooden_sword ? 'none' : 'inline-block';
                adventureLink.style.display = s.has_wooden_sword ? 'inline-block' : 'none';
            } else {
                shopLocked.style.display = 'block';
                shopContent.style.display = 'none';
                buySwordBtn.style.display = 'none';
                adventureLink.style.display = 'none';
            }
        }

        // local copy for smooth updates
        let local = Object.assign({}, state);
        render(local);

        // add candies locally at CPS rate every second
        setInterval(() => {
            local.candies += local.cps;
            render(local);
        }, 1000);

        // click handler
        document.getElementById('clickBtn').addEventListener('click', () => {
            fetch('/game/action', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf },
                body: JSON.stringify({ type: 'click' })
            }).then(r => r.json()).then(s => {
                local = s;
                render(local);
            }).catch(console.error);
        });

        // buys
        document.querySelectorAll('.buy').forEach(btn => {
            btn.addEventListener('click', () => {
                const item = btn.getAttribute('data-item');
                fetch('/game/action', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf },
                    body: JSON.stringify({ type: 'buy', item })
                }).then(r => r.json()).then(s => {
                    local = s;
                    render(local);
                }).catch(console.error);
            });
        });

        // wooden sword purchase
        document.getElementById('buySwordBtn').addEventListener('click', () => {
            fetch('/game/action', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf },
                body: JSON.stringify({ type: 'buy', item: 'wooden_sword' })
            }).then(r => r.json()).then(s => {
                local = s;
                render(local);
            }).catch(console.error);
        });

        // periodic sync: every 10s pull server state
        setInterval(() => {
            fetch('/game/action', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf },
                body: JSON.stringify({ type: 'save', state: local })
            }).then(r => r.json()).then(s => {
                local = s;
                render(local);
            }).catch(console.error);
        }, 10000);
    </script>
</body>
</html>
