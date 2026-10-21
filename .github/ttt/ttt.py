"""Jogo da velha do perfil.

Cada clique numa casa do README abre uma issue com o titulo "ttt|jogar|N".
A Action chama este script, que aplica a jogada do visitante (X), deixa a IA
responder (O, minimax) e redesenha o tabuleiro entre os marcadores do README.

Uso:
  ISSUE_TITLE="ttt|jogar|5" PLAYER=login COMMENT_FILE=saida.md python ttt.py
  python ttt.py --render   # so redesenha o README a partir do estado salvo
"""
import json
import os
import re
import sys
from functools import lru_cache
from pathlib import Path
from urllib.parse import quote

ROOT = Path(__file__).resolve().parents[2]
STATE_FILE = Path(__file__).resolve().parent / "state.json"
README = ROOT / "README.md"
REPO = os.environ.get("GITHUB_REPOSITORY", "devMendes23/devMendes23")
START, END = "<!-- JOGO:INICIO -->", "<!-- JOGO:FIM -->"

HUMAN, AI = "X", "O"
AI_PLAYER = "~ia"  # "~" nao existe em login do GitHub, entao nao colide com ninguem
LINES = [(0, 1, 2), (3, 4, 5), (6, 7, 8), (0, 3, 6), (1, 4, 7), (2, 5, 8), (0, 4, 8), (2, 4, 6)]
PREFERENCE = [4, 0, 2, 6, 8, 1, 3, 5, 7]
COMMAND = re.compile(r"^ttt\|jogar\|([1-9])$")
LOGIN = re.compile(r"^[A-Za-z0-9](?:[A-Za-z0-9-]{0,38})$")

ISSUE_BODY = (
    "👇 É só clicar em **Create** (ou *Submit new issue*).\n\n"
    "Em cerca de 30 segundos a IA responde aqui e o tabuleiro atualiza no perfil."
)


def empty_state():
    return {
        "board": [""] * 9,
        "moves": [],
        "last_ai": None,
        "score": {"humanos": 0, "ia": 0, "empates": 0},
        "players": {},
        "last_game": None,
    }


def load():
    if STATE_FILE.exists():
        return json.loads(STATE_FILE.read_text(encoding="utf-8"))
    return empty_state()


def save(state):
    STATE_FILE.write_text(json.dumps(state, ensure_ascii=False, indent=2) + "\n", encoding="utf-8")


# ---------------------------------------------------------------- regras e IA

def outcome(board):
    """'X' ou 'O' para quem venceu, 'empate' se lotou, None se o jogo segue."""
    for a, b, c in LINES:
        if board[a] and board[a] == board[b] == board[c]:
            return board[a]
    return "empate" if all(board) else None


@lru_cache(maxsize=None)
def score(board, turn):
    """Minimax: positivo e bom para a IA. Vitorias rapidas valem mais que as demoradas."""
    result = outcome(board)
    if result == AI:
        return 10 + board.count("")
    if result == HUMAN:
        return -10 - board.count("")
    if result == "empate":
        return 0
    nxt = HUMAN if turn == AI else AI
    results = [score(board[:i] + (turn,) + board[i + 1:], nxt) for i in range(9) if not board[i]]
    return max(results) if turn == AI else min(results)


def best_move(board):
    frozen = tuple(board)
    options = [i for i in PREFERENCE if not frozen[i]]
    return max(options, key=lambda i: score(frozen[:i] + (AI,) + frozen[i + 1:], HUMAN))


# ---------------------------------------------------------------- partida

def finish(state, result):
    players = []
    for move in state["moves"]:
        if move["p"] != AI_PLAYER and move["p"] not in players:
            players.append(move["p"])
    key = {"X": "humanos", "O": "ia", "empate": "empates"}[result]
    state["score"][key] += 1
    state["last_game"] = {"resultado": key, "jogadores": players, "tabuleiro": state["board"]}
    state["board"] = [""] * 9
    state["moves"] = []
    state["last_ai"] = None
    return key


def play(state, cell, player):
    """Aplica a jogada e devolve o texto do comentario da issue."""
    board = state["board"]
    if board[cell]:
        return f"Ops, a casa **{cell + 1}** já foi ocupada (alguém jogou antes de você). Escolha outra casa vazia no perfil 🙂"

    board[cell] = HUMAN
    state["moves"].append({"p": player, "c": cell})
    state["players"][player] = state["players"].get(player, 0) + 1
    lines = [f"Você jogou ❌ na casa **{cell + 1}**."]

    result = outcome(board)
    if result is None:
        ai = best_move(board)
        board[ai] = AI
        state["moves"].append({"p": AI_PLAYER, "c": ai})
        state["last_ai"] = ai
        lines.append(f"A IA respondeu com ⭕ na casa **{ai + 1}**.")
        result = outcome(board)

    lines += ["", render_board_text(board)]
    if result:
        key = finish(state, result)
        lines.append("")
        lines.append({
            "humanos": "🏆 **Você venceu a IA!** Isso não devia ser possível… vou revisar o meu minimax.",
            "ia": "🤖 **A IA venceu desta vez.** Tente de novo: o tabuleiro já foi zerado no perfil.",
            "empates": "🤝 **Empate!** Contra um minimax isso é um ótimo resultado. Nova partida já está no perfil.",
        }[key])
    else:
        lines += ["", "Sua vez de novo (ou de qualquer outra pessoa): o tabuleiro já está atualizado no perfil."]

    lines += ["", f"[↩ Voltar ao perfil](https://github.com/{REPO.split('/')[0]})"]
    return "\n".join(lines)


def render_board_text(board):
    sym = {"X": "❌", "O": "⭕", "": "⬜"}
    return "\n".join(" ".join(sym[board[r * 3 + c]] for c in range(3)) for r in range(3))


# ---------------------------------------------------------------- README

def user_link(login):
    if login == "anonimo":
        return "alguém"
    return f'<a href="https://github.com/{login}">@{login}</a>'


def render_section(state):
    board, last_ai = state["board"], state["last_ai"]
    rows = []
    for r in range(3):
        cells = []
        for c in range(3):
            i = r * 3 + c
            mark = board[i]
            if mark == HUMAN:
                cells.append(f'<a href="#jogo"><img src="assets/ttt/x.svg" width="92" alt="Casa {i + 1}: X"></a>')
            elif mark == AI:
                img = "o-new.svg" if i == last_ai else "o.svg"
                cells.append(f'<a href="#jogo"><img src="assets/ttt/{img}" width="92" alt="Casa {i + 1}: O"></a>')
            else:
                url = (
                    f"https://github.com/{REPO}/issues/new?title={quote(f'ttt|jogar|{i + 1}')}"
                    f"&body={quote(ISSUE_BODY)}"
                )
                cells.append(f'<a href="{url}"><img src="assets/ttt/empty-{i + 1}.svg" width="92" alt="Jogar na casa {i + 1}"></a>')
        rows.append("".join(cells))

    s = state["score"]
    out = [START, '<p align="center">', "<br>\n".join(rows), "</p>", ""]
    if state["moves"]:
        trail = []
        for move in state["moves"][-6:]:
            who = "🤖 IA" if move["p"] == AI_PLAYER else user_link(move["p"])
            trail.append(f"{who} {'⭕' if move['p'] == AI_PLAYER else '❌'}{move['c'] + 1}")
        out.append(f'<p align="center"><sub>Nesta partida: {" → ".join(trail)}</sub></p>')
    else:
        out.append('<p align="center"><sub>Tabuleiro novinho: faça a primeira jogada!</sub></p>')
    out.append(
        f'<p align="center"><b>Placar da comunidade</b><br>'
        f'🧑 Humanos <b>{s["humanos"]}</b> &nbsp;·&nbsp; 🤖 IA <b>{s["ia"]}</b> &nbsp;·&nbsp; 🤝 Empates <b>{s["empates"]}</b></p>'
    )

    last = state["last_game"]
    if last:
        label = {"humanos": "🏆 humanos venceram", "ia": "🤖 a IA venceu", "empates": "🤝 empate"}[last["resultado"]]
        who = ", ".join(user_link(p) for p in last["jogadores"][:5]) or "anônimos"
        out.append(f'<p align="center"><sub>Última partida: {label} · jogaram {who}</sub></p>')

    top = sorted(state["players"].items(), key=lambda kv: (-kv[1], kv[0].lower()))[:3]
    if top:
        medals = ["🥇", "🥈", "🥉"]
        ranking = " &nbsp; ".join(f"{medals[k]} {user_link(p)} ({n})" for k, (p, n) in enumerate(top))
        out.append(f'<p align="center"><sub>Quem mais jogou: {ranking}</sub></p>')
    out.append(END)
    return "\n".join(out)


def update_readme(state):
    text = README.read_text(encoding="utf-8")
    pattern = re.compile(re.escape(START) + r".*?" + re.escape(END), re.S)
    if not pattern.search(text):
        sys.exit(f"Marcadores {START} / {END} não encontrados no README")
    README.write_text(pattern.sub(lambda _: render_section(state), text, count=1), encoding="utf-8")


# ---------------------------------------------------------------- entrada

def main():
    state = load()
    if "--render" in sys.argv:
        update_readme(state)
        return

    title = os.environ.get("ISSUE_TITLE", "").strip()
    player = os.environ.get("PLAYER", "").strip()
    if not LOGIN.match(player):
        player = "anonimo"
    match = COMMAND.match(title)
    if match:
        message = play(state, int(match.group(1)) - 1, player)
        save(state)
        update_readme(state)
    else:
        message = "Não entendi essa jogada. Volte ao perfil e clique numa casa vazia do tabuleiro 🙂"

    comment_file = os.environ.get("COMMENT_FILE")
    if comment_file:
        Path(comment_file).write_text(message + "\n", encoding="utf-8")
    print(message)


if __name__ == "__main__":
    main()
