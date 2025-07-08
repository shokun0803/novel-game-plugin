document.addEventListener("DOMContentLoaded", function () {
    let dialogueIndex = 0;
    let dialogues = JSON.parse(document.getElementById("novel-dialogue-data").textContent);
    let choices = JSON.parse(document.getElementById("novel-choices-data").textContent);
    
    function showNextDialogue() {
        if (dialogueIndex < dialogues.length) {
            document.getElementById("novel-dialogue-text").innerText = dialogues[dialogueIndex];
            dialogueIndex++;
        } else {
            document.getElementById("novel-dialogue-box").style.display = "none";
            document.getElementById("novel-choices").style.display = "block";
            showChoices();
        }
    }
    
    function showChoices() {
        let choiceContainer = document.getElementById("novel-choices");
        choices.forEach(choice => {
            let btn = document.createElement("button");
            btn.innerText = choice.text;
            btn.onclick = () => {
                window.location.href = choice.nextScene;
            };
            choiceContainer.appendChild(btn);
        });
    }

    document.getElementById("novel-game-container").addEventListener("click", showNextDialogue);
    showNextDialogue();
});
