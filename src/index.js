console.log('Hello, World!');
import { select } from '@inquirer/prompts';

const answer = await select({
  message: 'What\'s your current mood',
  choices: [
    {
      name: 'happy',
      value: 'happy',
      description: 'I am feeling happy today!',
    },
    {
      name: 'suicidal',
      value: 'suicidal',
      description: 'I am feeling suicidal today!',
    },
    {
      name: 'sad',
      value: 'sad',
      description: 'I am feeling suicidal today!',
    },
    {
      name: 'dangerous',
      value: 'dangerous',
      description: 'I am feeling suicidal today!',
    },
  ],
});

console.log(`Va creuver la bouche ouverte, ${answer} boii!`);