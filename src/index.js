console.log('Hello, World!');
import { select } from '@inquirer/prompts';
import * as cowsay from "cowsay"

const answer = await select({
  message: 'What\'s your current mood',
  choices: ['sad', 'happy', 'excited', 'angry'],
});

const runQuiz = async () => {
  const answers = await prompt(questions);
  const results = calculateResult(Object.values(answers).map(answer => answer.value));
  console.log(`Your totem fish is: ${results.fish}! ${results.description}`);
  process.exit();
};

console.log(cowsay.say({
    text : `I'm f*cking ${answer}, coward!`,
    f : "banana"
}));
