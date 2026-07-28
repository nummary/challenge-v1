from datetime import datetime

deadline = datetime(2026, 8, 31, 0, 0, 0)
now = datetime.utcnow()

delta = deadline - now

days = delta.days
hours = delta.seconds // 3600

text = f"**Осталось:** {days} дней {hours} часов"

with open("README.md", "r", encoding="utf-8") as f:
    readme = f.read()

start = "<!--COUNTDOWN_START-->"
end = "<!--COUNTDOWN_END-->"

before = readme.split(start)[0]
after = readme.split(end)[1]

new = before + start + "\n" + text + "\n" + end + after

with open("README.md", "w", encoding="utf-8") as f:
    f.write(new)
