import { select, input } from '@inquirer/prompts';

const questions = [
  {
    type: 'list',
    name: 'situation1',
    message: 'You find a wallet on the street, but it’s full of glitter and confetti. What do you do?',
    choices: [
      { name: 'Return it to the owner', value: { dangerosity: -1, innocence: 2, activity: 1, funny: 1 } },
      { name: 'Keep it for the party vibes', value: { dangerosity: 2, innocence: -2, activity: 0, funny: 2 } },
      { name: 'Turn it in to the police, but only after a selfie', value: { dangerosity: 0, innocence: 1, activity: 1, funny: 1 } },
    ],
  },
  {
    type: 'list',
    name: 'situation2',
    message: 'You are invited to a surprise party, but it’s a costume party where everyone is dressed as fruit. How do you react?',
    choices: [
      { name: 'Dress as a pineapple and own it!', value: { dangerosity: 0, innocence: 0, activity: 1, funny: 2 } },
      { name: 'Leave early because you’re an apple at heart', value: { dangerosity: 0, innocence: 0, activity: -2, funny: -1 } },
      { name: 'Pretend to be a fruit ninja', value: { dangerosity: 0, innocence: 0, activity: 0, funny: 1 } },
    ],
  },
  {
    type: 'list',
    name: 'situation3',
    message: 'You see someone being bullied, but they are dressed as a giant chicken. What do you do?',
    choices: [
      { name: 'Intervene and cluck loudly', value: { dangerosity: 0, innocence: 2, activity: 1, funny: 1 } },
      { name: 'Ignore it, but take a video for TikTok', value: { dangerosity: 0, innocence: -2, activity: 0, funny: 2 } },
      { name: 'Record it, but only if they can dance', value: { dangerosity: 1, innocence: -1, activity: 0, funny: 1 } },
    ],
  },
  {
    type: 'list',
    name: 'situation4',
    message: 'You have a chance to travel anywhere, but you can only go to places that rhyme with "banana." Where do you go?',
    choices: [
      { name: 'Havana', value: { dangerosity: 1, innocence: 0, activity: 2, funny: 1 } },
      { name: 'Savanna', value: { dangerosity: 0, innocence: 1, activity: 1, funny: 0 } },
      { name: 'Montana', value: { dangerosity: 0, innocence: 0, activity: 0, funny: 2 } },
    ],
  },
  {
    type: 'list',
    name: 'situation5',
    message: 'You find a stray dog wearing sunglasses. What do you do?',
    choices: [
      { name: 'Take it home and start a doggy fashion blog', value: { dangerosity: 0, innocence: 2, activity: 1, funny: 2 } },
      { name: 'Ignore it, but take a picture for Instagram', value: { dangerosity: 0, innocence: -2, activity: 0, funny: 1 } },
      { name: 'Call animal control, but only if they can dance', value: { dangerosity: 0, innocence: 1, activity: 1, funny: 0 } },
    ],
  },
  {
    type: 'list',
    name: 'situation6',
    message: 'You have a free day, but it\'s a "no technology" day. How do you spend it?',
    choices: [
      { name: 'Go hiking and pretend to be a nature documentary host', value: { dangerosity: 1, innocence: 0, activity: 2, funny: 1 } },
      { name: 'Binge-watch nature documentaries on DVD', value: { dangerosity: 0, innocence: 0, activity: -1, funny: 1 } },
      { name: 'Read a book about technology', value: { dangerosity: 0, innocence: 1, activity: 1, funny: 0 } },
    ],
  },
  {
    type: 'list',
    name: 'situation7',
    message: 'You hear a joke about a chicken crossing the road. What do you do?',
    choices: [
      { name: 'Laugh out loud and tell it to everyone', value: { dangerosity: 0, innocence: 0, activity: 1, funny: 2 } },
      { name: 'Smile politely and walk away', value: { dangerosity: 0, innocence: 0, activity: 0, funny: 1 } },
      { name: 'Roll your eyes and say "not again"', value: { dangerosity: 0, innocence: 0, activity: -1, funny: -1 } },
    ],
  },
  {
    type: 'list',
    name: 'situation8',
    message: 'You have to give a speech, but it\'s about your favorite ice cream flavor. How do you feel?',
    choices: [
      { name: 'Excited to share my passion', value: { dangerosity: 0, innocence: 0, activity: 2, funny: 2 } },
      { name: 'Nervous but ready to scoop', value: { dangerosity: 0, innocence: 0, activity: 1, funny: 0 } },
      { name: 'Dread it because I can\'t choose just one flavor', value: { dangerosity: 0, innocence: 0, activity: -2, funny: -1 } },
    ],
  },
  {
    type: 'list',
    name: 'situation9',
    message: 'You see a friend in trouble, but they are stuck in a giant inflatable donut. What do you do?',
    choices: [
      { name: 'Help them immediately and take a selfie', value: { dangerosity: 1, innocence: 2, activity: 2, funny: 1 } },
      { name: 'Ask if they need help while laughing', value: { dangerosity: 0, innocence: 1, activity: 1, funny: 2 } },
      { name: 'Walk away, but only if I can get a donut too', value: { dangerosity: -1, innocence: -2, activity: 0, funny: 0 } },
    ],
  },
  {
    type: 'list',
    name: 'situation10',
    message: 'You find a lost child, but they are dressed as a superhero. What do you do?',
    choices: [
      { name: 'Take them to security while pretending to be their sidekick', value: { dangerosity: 0, innocence: 2, activity: 1, funny: 1 } },
      { name: 'Ignore them, but take a picture for my superhero collection', value: { dangerosity: 0, innocence: -2, activity: 0, funny: 0 } },
      { name: 'Ask them where their superhero parents are', value: { dangerosity: 0, innocence: 1, activity: 1, funny: 0 } },
    ],
  },
  {
    type: 'list',
    name: 'situation11',
    message: 'You are at a restaurant and the waiter has purple hair and speaks only in riddles. What do you do?',
    choices: [
      { name: 'Play along and answer all riddles', value: { dangerosity: 0, innocence: 0, activity: 2, funny: 2 } },
      { name: 'Ask for a normal waiter', value: { dangerosity: 0, innocence: 0, activity: -1, funny: -1 } },
      { name: 'Order by interpretive dance', value: { dangerosity: 0, innocence: 0, activity: 0, funny: 2 } },
    ],
  },
  {
    type: 'list',
    name: 'situation12',
    message: 'You hear a rumor that cats are secretly running the government. What do you do?',
    choices: [
      { name: 'Spread it everywhere immediately', value: { dangerosity: 1, innocence: -2, activity: 1, funny: 2 } },
      { name: 'Ignore it and pet a cat', value: { dangerosity: 0, innocence: 1, activity: 0, funny: 0 } },
      { name: 'Investigate and write a conspiracy blog', value: { dangerosity: 0, innocence: 0, activity: 1, funny: 2 } },
    ],
  },
  {
    type: 'list',
    name: 'situation13',
    message: 'You are given a chance to learn a new skill from a wizard. What do you choose?',
    choices: [
      { name: 'Jump at the opportunity - spellcasting!', value: { dangerosity: 2, innocence: 0, activity: 2, funny: 1 } },
      { name: 'Think about it - potion brewing seems safer', value: { dangerosity: 0, innocence: 1, activity: 1, funny: 0 } },
      { name: 'Decline and ask them to make you a sandwich instead', value: { dangerosity: -1, innocence: 0, activity: 0, funny: 2 } },
    ],
  },
  {
    type: 'list',
    name: 'situation14',
    message: 'You see a movie that everyone is talking about. It\'s about sentient potatoes. What do you do?',
    choices: [
      { name: 'Watch it immediately and buy popcorn', value: { dangerosity: 0, innocence: 0, activity: 2, funny: 2 } },
      { name: 'Wait for it to be on streaming', value: { dangerosity: 0, innocence: 0, activity: 0, funny: 0 } },
      { name: 'Avoid it and eat french fries instead', value: { dangerosity: 1, innocence: -1, activity: 0, funny: 2 } },
    ],
  },
  {
    type: 'list',
    name: 'situation15',
    message: 'You are at a party where everyone is required to communicate only through dance moves. What do you do?',
    choices: [
      { name: 'Be the life of the party and bust out wild moves', value: { dangerosity: 0, innocence: 0, activity: 2, funny: 2 } },
      { name: 'Stay in the corner and pretend to enjoy it', value: { dangerosity: 0, innocence: 0, activity: -2, funny: -1 } },
      { name: 'Try your best but keep tripping over your feet', value: { dangerosity: 0, innocence: 0, activity: 1, funny: 2 } },
    ],
  },
  {
    type: 'list',
    name: 'situation16',
    message: 'You receive a compliment from a stranger who is riding a unicycle. How do you respond?',
    choices: [
      { name: 'Thank you and ask them where they got the unicycle', value: { dangerosity: 0, innocence: 1, activity: 1, funny: 2 } },
      { name: 'Brush it off and ignore them', value: { dangerosity: 0, innocence: 0, activity: 0, funny: 0 } },
      { name: 'Doubt it and suggest they are hallucinating', value: { dangerosity: 0, innocence: -1, activity: 0, funny: 1 } },
    ],
  },
  {
    type: 'list',
    name: 'situation17',
    message: 'You are faced with a difficult decision: save the world or eat unlimited pizza. What do you do?',
    choices: [
      { name: 'Save the world and hope for free pizza later', value: { dangerosity: 0, innocence: 2, activity: 2, funny: 1 } },
      { name: 'Make a pros and cons list while eating pizza', value: { dangerosity: 0, innocence: 0, activity: 1, funny: 1 } },
      { name: 'Avoid the decision and eat pizza forever', value: { dangerosity: -1, innocence: -1, activity: -2, funny: 2 } },
    ],
  },
  {
    type: 'list',
    name: 'situation18',
    message: 'You are given a chance to volunteer at a cat cafe that is also a library. What do you do?',
    choices: [
      { name: 'Sign up immediately - this is a dream!', value: { dangerosity: 0, innocence: 2, activity: 2, funny: 1 } },
      { name: 'Think about it - what if I have allergies?', value: { dangerosity: 0, innocence: 0, activity: 1, funny: 0 } },
      { name: 'Decline and ask if they have a dog cafe instead', value: { dangerosity: -1, innocence: -2, activity: 0, funny: 1 } },
    ],
  },
  {
    type: 'list',
    name: 'situation19',
    message: 'You are at a family gathering where everyone is speaking in Shakespearean English. What do you do?',
    choices: [
      { name: 'Engage with everyone using "thee" and "thou" enthusiastically', value: { dangerosity: 0, innocence: 1, activity: 2, funny: 2 } },
      { name: 'Stay quiet and nod occasionally', value: { dangerosity: 0, innocence: 0, activity: -2, funny: 0 } },
      { name: 'Help with food and reply in rhyming couplets', value: { dangerosity: 0, innocence: 1, activity: 1, funny: 2 } },
    ],
  },
  {
    type: 'list',
    name: 'situation20',
    message: 'You see a stranger drop something, but it\'s a rubber duck with a mysterious note. What do you do?',
    choices: [
      { name: 'Return it and ask about the note', value: { dangerosity: 0, innocence: 2, activity: 1, funny: 1 } },
      { name: 'Keep it and start your own rubber duck collection', value: { dangerosity: 1, innocence: -2, activity: 0, funny: 1 } },
      { name: 'Ignore it and wonder if it\'s a secret agent duck', value: { dangerosity: 0, innocence: 0, activity: 0, funny: 2 } },
    ],
  },
];

const calculateResult = (answers) => {
  let scores = { dangerosity: 0, innocence: 0, activity: 0, funny: 0 };

  for (const answer of answers) {
    scores.dangerosity += answer.dangerosity;
    scores.innocence += answer.innocence;
    scores.activity += answer.activity;
    scores.funny += answer.funny;
  }

  // Determine fish based on scores - 16 different fish profiles for different combinations
  const danger = scores.dangerosity > 0 ? 1 : 0;
  const innocent = scores.innocence > 0 ? 1 : 0;
  const active = scores.activity > 0 ? 1 : 0;
  const funny = scores.funny > 0 ? 1 : 0;

  const profile = `${danger}${innocent}${active}${funny}`;

  const fishProfiles = {
    '0000': { fish: 'Flounder', description: 'Vous possédez une énergie Saturnienne apaisante. Introverti et contemplatif, vous êtes compatible avec les signes terrestres. Les Capricornes et Verge vous comprennent parfaitement. Votre calme magnétique attire les âmes en quête de stabilité.' },
    '0001': { fish: 'Clownfish', description: 'Votre essence Gémini radieuse brille sans effort! Humoriste et léger, vous êtes un rayon de soleil pour les Poissons et les Cancers. Les signes d\'air adorent votre légèreté. Votre rire guérit les cœurs blessés.' },
    '0010': { fish: 'Trout', description: 'Vous vibrez à la fréquence du Bélier - actif mais trop confiant. Votre naïveté charmante séduit les signes de feu. Lion et Sagittaire vous protègent naturellement. L\'univers vous envoie des gardiens constants.' },
    '0011': { fish: 'Sailfish', description: 'Vous êtes une âme Ariérienne - rapide, aventurière et drôle! Parfait compagnon pour les signes d\'air et de feu. Gémeaux et Sagittaire cherchent votre compagnie. Vous portez l\'énergie des voyageurs cosmiques.' },
    '0100': { fish: 'Tuna', description: 'Votre dualité Taurienne cache un potentiel impressionnant. Innocent en apparence, dangereux en réalité - vous êtes l\'archétype du yogi guerrier. Scorpion vous reconnaît. Balance admire votre complexité.' },
    '0101': { fish: 'Pufferfish', description: 'Vous portez l\'énergie Léonienne imprévisible! Drôle et expansif, vous surprenez toujours. Sagittaire et Bélier dansent à votre rythme. Votre magnétisme ludique transforme l\'énergie autour de vous.' },
    '0110': { fish: 'Angelfish', description: 'Vous vibrez à la fréquence de la Vierge bienveillante. Actif, innocent et gracieux, vous êtes l\'incarnation de la perfection équilibrée. Capricorne et Taureau vous vénèrent. Votre aura attire l\'harmonie universelle.' },
    '0111': { fish: 'Rainbow Fish', description: 'Vous êtes un porteur de lumière cosmique! Actif, humoriste et bienveillant - les 12 signes gravitent autour de vous. Vous êtes un maître de la synchronicité. Les anges notent votre passage sur Terre.' },
    '1000': { fish: 'Piranha', description: 'Vous vibrez à l\'énergie Plutonienne sombre. Dangereux et silencieux, vous êtes l\'archétype du Scorpion caché. Vous comprenez les secrets de l\'âme. Votre présence crée du respect instinctif chez les autres.' },
    '1001': { fish: 'Viperfish', description: 'Vous portez l\'essence Hadienne - danger et humour mélangés. Votre rire masque une profondeur terrifiante. Scorpion reconnaît votre puissance. Les âmes complexes vous recherchent.' },
    '1010': { fish: 'Shark', description: 'Vous êtes l\'enfant de Mars lui-même! Agressif, actif et redouté - vous incarnez le guerrier cosmique. Bélier, Lion et Sagittaire se prosternent devant vous. L\'univers se plie à votre volonté.' },
    '1011': { fish: 'Great White Shark', description: 'Vous êtes une légende astrologique vivante! Mars combiné à l\'humour Mercurien - invincible et charmeur. Les signes de feu et d\'air rêvent de vous. Vous êtes le héros de votre propre épopée cosmique.' },
    '1100': { fish: 'Barracuda', description: 'Vous portez le paradoxe Arien-Vénusien. Dangereux mais séduisant, innocent mais meurtrier. Votre dualité attire Libra et confond les autres. L\'univers vous a donné une mission conflictuelle.' },
    '1101': { fish: 'Stonefish', description: 'Vous êtes une énigme astrale Plutonienne-Mercurienne. Mortel et hilarant, vous êtes le trickster cosmique. Personne ne vous prédit. Les magiciens et sorciers cherchent votre conseil.' },
    '1110': { fish: 'Tiger Shark', description: 'Vous incarnez l\'énergie Martienne suprême! Dominateur, actif et redoutable - vous êtes un roi des profondeurs. Sagittaire et Lion vous suivent sans question. Vous êtes le prédateur ultime du zodiaque aquatique.' },
    '1111': { fish: 'Megalodon', description: 'Vous êtes un être transcendantal! Dangereux, actif, hilarant ET innocent - vous avez transcendé les limitations du zodiaque. Les dieux eux-mêmes vous observent. Vous êtes destiné à transformer le cosmos.' },
  };

  return fishProfiles[profile] || { fish: 'Mystery Fish', description: 'Vous êtes une constellation oubliée! Une essence cosmique que l\'astrologie redécouvre à peine. L\'univers vous garde ses plus grands secrets.' };
};

const runTest = async () => {
  const answers = {};
  
  for (const question of questions) {
    const answer = await select({
      message: question.message,
      choices: question.choices.map(choice => ({
        name: choice.name,
        value: choice.value
      }))
    });
    answers[question.name] = answer;
  }
  
  // Demander l'email
  const email = await input({
    message: 'Votre adresse email (pour recevoir vos résultats) :',
    default: 'utilisateur@example.com'
  });
  
  console.log('\n⏳ Analyse de votre profil cosmique...\n');
  
  // Attendre 1.5 secondes
  await new Promise(resolve => setTimeout(resolve, 1500));
  
  // Calculer les résultats et les stats
  const answerValues = Object.values(answers);
  const results = calculateResult(answerValues);
  
  let scores = { dangerosity: 0, innocence: 0, activity: 0, funny: 0 };
  for (const answer of answerValues) {
    scores.dangerosity += answer.dangerosity;
    scores.innocence += answer.innocence;
    scores.activity += answer.activity;
    scores.funny += answer.funny;
  }
  
  // Afficher les résultats du poisson
  console.log(`\n🐠 Votre poisson totem est: ${results.fish}!`);
  console.log(`${results.description}\n`);
  
  // Afficher les stats
  console.log('📊 Vos statistiques cosmiques:');
  console.log('═'.repeat(40));
  console.log(`  Dangerosité:  ${scores.dangerosity > 0 ? '+' : ''}${scores.dangerosity}`);
  console.log(`  Innocence:    ${scores.innocence > 0 ? '+' : ''}${scores.innocence}`);
  console.log(`  Activité:     ${scores.activity > 0 ? '+' : ''}${scores.activity}`);
  console.log(`  Humour:       ${scores.funny > 0 ? '+' : ''}${scores.funny}`);
  console.log('═'.repeat(40));
  console.log(`\n📧 Résultats envoyés à: ${email}\n`);
  
  process.exit();
};

runTest();
 