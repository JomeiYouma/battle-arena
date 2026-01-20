console.log('Hello, World!');
import { select } from '@inquirer/prompts';
import * as cowsay from "cowsay"

const answer = await select({
  message: 'What\'s your current mood',
  choices: ['sad', 'happy', 'excited', 'angry'],
});

console.log(cowsay.say({
    text : `I'm f*cking ${answer}, coward!`,
    f : "banana"
}));
