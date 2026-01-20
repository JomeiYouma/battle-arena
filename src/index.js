console.log('Hello, World!');
import { input } from '@inquirer/prompts';

const answer = await input({ message: 'Enter your name' });
console.log(`Hello, ${answer}!`);
