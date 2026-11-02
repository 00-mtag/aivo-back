<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ExerciseSeeder extends Seeder
{
    private const BEST_FOR = [
        'Build muscle, Lose weight, Improve endurance, Maintain performance' => [
            'Bodyweight Squat',
            'Reverse Lunge',
            'Forward Lunge',
            'Walking Lunge',
            'Step-Up',
            'Goblet Squat',
            'Push-Up',
            'Knee Push-Up',
            'Incline Push-Up',
            'Decline Push-Up',
            'Dips',
            'Mountain Climber',
            'Burpee',
            'Resistance Band Row',
            'Resistance Band Chest Press',
            'Kettlebell Swing',
        ],
        'Build muscle, Maintain performance' => [
            'Glute Bridge',
            'Single-Leg Glute Bridge',
            'Calf Raise',
            'Romanian Deadlift',
            'Barbell Deadlift',
            'Dumbbell Deadlift',
            'Leg Press',
            'Leg Extension',
            'Seated Leg Curl',
            'Barbell Back Squat',
            'Barbell Front Squat',
            'Dumbbell Shoulder Press',
            'Barbell Overhead Press',
            'Lateral Raise',
            'Front Raise',
            'Dumbbell Bench Press',
            'Barbell Bench Press',
            'Incline Dumbbell Press',
            'Chest Fly',
            'Cable Chest Fly',
            'Triceps Chair Dip',
            'Triceps Pushdown',
            'Overhead Triceps Extension',
            'Biceps Curl',
            'Hammer Curl',
            'Barbell Curl',
            'Pull-Up',
            'Assisted Pull-Up',
            'One-Arm Dumbbell Row',
            'Bent-Over Barbell Row',
            'Seated Cable Row',
            'Lat Pulldown',
            'Dumbbell Shrug',
            'Dumbbell Chest-Supported Row',
            'Cable Biceps Curl',
            'Machine Chest Press',
            'Machine Shoulder Press',
            'Hip Thrust',
        ],
        'Build muscle, Improve endurance, Maintain performance' => [
            'Wall Sit',
            'Inverted Row',
            'Face Pull',
            'Resistance Band Pull-Apart',
        ],
        'Improve endurance, Maintain performance' => [
            'Plank',
            'Side Plank',
            'Dead Bug',
            'Bird Dog',
            'Bicycle Crunch',
            'Crunch',
            'Leg Raise',
            'Russian Twist',
            'Bodyweight Good Morning',
        ],
        'Lose weight, Improve endurance, Maintain performance' => [
            'Jumping Jack',
            'High Knees',
            'Treadmill Walk',
            'Stationary Bike',
            'Elliptical Trainer',
            'Rowing Machine',
        ],
    ];

    private const EXERCISES = [
        ['name' => 'Bodyweight Squat', 'description' => 'A lower-body squat using only body weight.', 'muscle_group' => 'Quadriceps, glutes', 'equipment' => 'Bodyweight (home or gym)', 'difficulty' => 'Beginner', 'instructions' => 'Stand with feet shoulder-width apart, sit hips back and down, then drive through your feet to stand.'],
        ['name' => 'Reverse Lunge', 'description' => 'A single-leg exercise for the legs and glutes.', 'muscle_group' => 'Quadriceps, glutes, hamstrings', 'equipment' => 'Bodyweight (home or gym)', 'difficulty' => 'Beginner', 'instructions' => 'Step one foot backward, lower until both knees are bent, then push through the front foot to return.'],
        ['name' => 'Forward Lunge', 'description' => 'A stepping lunge that builds single-leg strength.', 'muscle_group' => 'Quadriceps, glutes, hamstrings', 'equipment' => 'Bodyweight (home or gym)', 'difficulty' => 'Beginner', 'instructions' => 'Step forward, lower under control until both knees are bent, then push back to standing.'],
        ['name' => 'Walking Lunge', 'description' => 'Alternating lunges performed while moving forward.', 'muscle_group' => 'Quadriceps, glutes, hamstrings', 'equipment' => 'Bodyweight or dumbbells', 'difficulty' => 'Intermediate', 'instructions' => 'Take a controlled step forward into a lunge, then bring the back leg through and alternate.'],
        ['name' => 'Glute Bridge', 'description' => 'A floor exercise to strengthen the glutes and hips.', 'muscle_group' => 'Glutes, hamstrings', 'equipment' => 'Bodyweight (home or gym)', 'difficulty' => 'Beginner', 'instructions' => 'Lie on your back with knees bent, brace your core, lift hips by squeezing your glutes, then lower slowly.'],
        ['name' => 'Single-Leg Glute Bridge', 'description' => 'A unilateral glute bridge variation.', 'muscle_group' => 'Glutes, hamstrings', 'equipment' => 'Bodyweight (home or gym)', 'difficulty' => 'Intermediate', 'instructions' => 'Lift one foot, press through the other heel to raise your hips, then lower with control and switch sides.'],
        ['name' => 'Step-Up', 'description' => 'A single-leg movement using a stable step or bench.', 'muscle_group' => 'Quadriceps, glutes', 'equipment' => 'Step or bench; optional dumbbells', 'difficulty' => 'Beginner', 'instructions' => 'Place one whole foot on a stable step, drive through it to stand on top, then step down carefully.'],
        ['name' => 'Calf Raise', 'description' => 'A standing exercise for the lower legs.', 'muscle_group' => 'Calves', 'equipment' => 'Bodyweight (home or gym)', 'difficulty' => 'Beginner', 'instructions' => 'Stand tall, rise onto the balls of your feet, pause briefly, then lower your heels slowly.'],
        ['name' => 'Wall Sit', 'description' => 'An isometric lower-body exercise performed against a wall.', 'muscle_group' => 'Quadriceps, glutes', 'equipment' => 'Wall (home or gym)', 'difficulty' => 'Beginner', 'instructions' => 'Lean against a wall and slide down until your knees are comfortably bent; hold while breathing steadily.'],
        ['name' => 'Romanian Deadlift', 'description' => 'A hip-hinge movement emphasizing the hamstrings and glutes.', 'muscle_group' => 'Hamstrings, glutes, lower back', 'equipment' => 'Dumbbells or barbell', 'difficulty' => 'Intermediate', 'instructions' => 'With a soft knee bend, push hips back and lower the weight along your legs, then stand by driving hips forward.'],
        ['name' => 'Barbell Deadlift', 'description' => 'A compound lift involving the hips, legs, and back.', 'muscle_group' => 'Glutes, hamstrings, back, quadriceps', 'equipment' => 'Barbell', 'difficulty' => 'Advanced', 'instructions' => 'Stand close to the bar, brace your trunk, push through the floor to stand, then return the bar with control.'],
        ['name' => 'Dumbbell Deadlift', 'description' => 'A hip-hinge deadlift variation using dumbbells.', 'muscle_group' => 'Glutes, hamstrings, back', 'equipment' => 'Dumbbells', 'difficulty' => 'Beginner', 'instructions' => 'Hold dumbbells at your sides, hinge at the hips while keeping your back neutral, then stand tall.'],
        ['name' => 'Leg Press', 'description' => 'A machine-based compound exercise for the legs.', 'muscle_group' => 'Quadriceps, glutes, hamstrings', 'equipment' => 'Leg press machine', 'difficulty' => 'Beginner', 'instructions' => 'Place feet on the platform, lower it until knees are comfortably bent, then press without locking your knees.'],
        ['name' => 'Leg Extension', 'description' => 'An isolation exercise for the front of the thighs.', 'muscle_group' => 'Quadriceps', 'equipment' => 'Leg extension machine', 'difficulty' => 'Beginner', 'instructions' => 'Align knees with the machine pivot, extend your legs smoothly, then lower the pad under control.'],
        ['name' => 'Seated Leg Curl', 'description' => 'A machine exercise for the hamstrings.', 'muscle_group' => 'Hamstrings', 'equipment' => 'Seated leg curl machine', 'difficulty' => 'Beginner', 'instructions' => 'Adjust the machine so the pad sits above your heels, curl it down, then return slowly.'],
        ['name' => 'Barbell Back Squat', 'description' => 'A compound squat performed with a barbell across the upper back.', 'muscle_group' => 'Quadriceps, glutes, hamstrings', 'equipment' => 'Barbell and squat rack', 'difficulty' => 'Intermediate', 'instructions' => 'Set the bar securely across your upper back, brace, squat to a comfortable depth, then stand by pressing through your feet.'],
        ['name' => 'Barbell Front Squat', 'description' => 'A squat variation with the bar held at the front of the shoulders.', 'muscle_group' => 'Quadriceps, core, glutes', 'equipment' => 'Barbell and squat rack', 'difficulty' => 'Advanced', 'instructions' => 'Rest the bar across your front shoulders, keep your torso upright, squat under control, then stand.'],
        ['name' => 'Goblet Squat', 'description' => 'A squat variation holding one weight in front of the chest.', 'muscle_group' => 'Quadriceps, glutes, core', 'equipment' => 'Dumbbell or kettlebell', 'difficulty' => 'Beginner', 'instructions' => 'Hold a weight close to your chest, squat between your knees while keeping your chest lifted, then stand.'],
        ['name' => 'Dumbbell Shoulder Press', 'description' => 'An overhead press using a dumbbell in each hand.', 'muscle_group' => 'Shoulders, triceps', 'equipment' => 'Dumbbells', 'difficulty' => 'Beginner', 'instructions' => 'Start with weights at shoulder height, press overhead without arching your back, then lower slowly.'],
        ['name' => 'Barbell Overhead Press', 'description' => 'A standing barbell press for the shoulders and arms.', 'muscle_group' => 'Shoulders, triceps, core', 'equipment' => 'Barbell', 'difficulty' => 'Intermediate', 'instructions' => 'Hold the bar at shoulder height, brace your core, press overhead, then lower it to the starting position.'],
        ['name' => 'Lateral Raise', 'description' => 'An isolation movement targeting the sides of the shoulders.', 'muscle_group' => 'Shoulders', 'equipment' => 'Dumbbells or resistance bands', 'difficulty' => 'Beginner', 'instructions' => 'Raise light weights out to the sides to about shoulder height, then lower slowly without swinging.'],
        ['name' => 'Front Raise', 'description' => 'A shoulder exercise that raises the arms in front of the body.', 'muscle_group' => 'Shoulders', 'equipment' => 'Dumbbells or resistance bands', 'difficulty' => 'Beginner', 'instructions' => 'Lift the weights in front of you to shoulder height with a slight elbow bend, then lower under control.'],
        ['name' => 'Push-Up', 'description' => 'A floor-based pressing exercise using body weight.', 'muscle_group' => 'Chest, shoulders, triceps, core', 'equipment' => 'Bodyweight (home or gym)', 'difficulty' => 'Beginner', 'instructions' => 'Keep your body in a straight line, lower your chest toward the floor, then press back up.'],
        ['name' => 'Knee Push-Up', 'description' => 'A modified push-up with the knees supporting the lower body.', 'muscle_group' => 'Chest, shoulders, triceps', 'equipment' => 'Bodyweight (home or gym)', 'difficulty' => 'Beginner', 'instructions' => 'Keep a straight line from knees to head, lower your chest with control, then press back up.'],
        ['name' => 'Incline Push-Up', 'description' => 'A push-up variation with hands elevated on a stable surface.', 'muscle_group' => 'Chest, shoulders, triceps', 'equipment' => 'Stable bench or counter', 'difficulty' => 'Beginner', 'instructions' => 'Place hands on a stable elevated surface, keep your body straight, lower your chest, then press away.'],
        ['name' => 'Decline Push-Up', 'description' => 'A push-up variation with feet elevated on a stable surface.', 'muscle_group' => 'Chest, shoulders, triceps', 'equipment' => 'Stable bench or step', 'difficulty' => 'Intermediate', 'instructions' => 'Place feet on a stable elevation, brace your body, lower your chest, then push back up.'],
        ['name' => 'Dumbbell Bench Press', 'description' => 'A horizontal chest press performed with dumbbells.', 'muscle_group' => 'Chest, shoulders, triceps', 'equipment' => 'Dumbbells and bench', 'difficulty' => 'Beginner', 'instructions' => 'Lower dumbbells beside your chest with wrists stacked, then press upward smoothly without clanking them together.'],
        ['name' => 'Barbell Bench Press', 'description' => 'A compound horizontal press using a barbell and bench.', 'muscle_group' => 'Chest, shoulders, triceps', 'equipment' => 'Barbell, bench, rack', 'difficulty' => 'Intermediate', 'instructions' => 'Grip the bar evenly, lower it toward mid-chest with control, then press it up while keeping your shoulders stable.'],
        ['name' => 'Incline Dumbbell Press', 'description' => 'An upper-chest press performed on an inclined bench.', 'muscle_group' => 'Upper chest, shoulders, triceps', 'equipment' => 'Dumbbells and incline bench', 'difficulty' => 'Beginner', 'instructions' => 'Set the bench to a modest incline, lower dumbbells beside your upper chest, then press upward.'],
        ['name' => 'Chest Fly', 'description' => 'A chest isolation movement using a controlled arc.', 'muscle_group' => 'Chest', 'equipment' => 'Dumbbells and bench', 'difficulty' => 'Intermediate', 'instructions' => 'With a slight elbow bend, open your arms until you feel a gentle chest stretch, then bring them together.'],
        ['name' => 'Cable Chest Fly', 'description' => 'A cable-based chest exercise with continuous resistance.', 'muscle_group' => 'Chest', 'equipment' => 'Cable machine', 'difficulty' => 'Beginner', 'instructions' => 'Stand between cables, bring your hands together in front of your chest, then return with control.'],
        ['name' => 'Dips', 'description' => 'A bodyweight pressing movement performed on parallel bars.', 'muscle_group' => 'Chest, triceps, shoulders', 'equipment' => 'Parallel bars or dip station', 'difficulty' => 'Advanced', 'instructions' => 'Support yourself on the bars, bend your elbows to lower only as far as comfortable, then press back up.'],
        ['name' => 'Triceps Chair Dip', 'description' => 'A bodyweight triceps exercise using a stable chair or bench.', 'muscle_group' => 'Triceps, shoulders', 'equipment' => 'Stable chair or bench', 'difficulty' => 'Intermediate', 'instructions' => 'Place hands on a stable edge, bend elbows to lower a short distance, then press back up while keeping shoulders comfortable.'],
        ['name' => 'Triceps Pushdown', 'description' => 'A cable isolation exercise for the triceps.', 'muscle_group' => 'Triceps', 'equipment' => 'Cable machine and bar or rope', 'difficulty' => 'Beginner', 'instructions' => 'Keep elbows near your sides, press the handle downward by straightening your arms, then return slowly.'],
        ['name' => 'Overhead Triceps Extension', 'description' => 'An arm exercise that trains the triceps overhead.', 'muscle_group' => 'Triceps', 'equipment' => 'Dumbbell or resistance band', 'difficulty' => 'Beginner', 'instructions' => 'Hold a weight overhead, bend your elbows to lower it behind your head, then extend your arms.'],
        ['name' => 'Biceps Curl', 'description' => 'A basic curl for the front of the upper arm.', 'muscle_group' => 'Biceps', 'equipment' => 'Dumbbells or resistance bands', 'difficulty' => 'Beginner', 'instructions' => 'Keep elbows close to your sides, curl the weights toward your shoulders, then lower them slowly.'],
        ['name' => 'Hammer Curl', 'description' => 'A neutral-grip curl for the arms and forearms.', 'muscle_group' => 'Biceps, forearms', 'equipment' => 'Dumbbells', 'difficulty' => 'Beginner', 'instructions' => 'Hold dumbbells with palms facing inward, curl without moving your elbows, then lower under control.'],
        ['name' => 'Barbell Curl', 'description' => 'A standing arm curl using a barbell.', 'muscle_group' => 'Biceps', 'equipment' => 'Barbell or EZ-bar', 'difficulty' => 'Beginner', 'instructions' => 'Stand tall, curl the bar toward your shoulders without swinging, then lower it slowly.'],
        ['name' => 'Pull-Up', 'description' => 'A bodyweight vertical pull performed from an overhead bar.', 'muscle_group' => 'Back, biceps', 'equipment' => 'Pull-up bar', 'difficulty' => 'Advanced', 'instructions' => 'Hang from a secure bar, pull your chest upward by driving elbows down, then lower with control.'],
        ['name' => 'Assisted Pull-Up', 'description' => 'A pull-up variation using assistance to reduce the load.', 'muscle_group' => 'Back, biceps', 'equipment' => 'Assisted pull-up machine or resistance band', 'difficulty' => 'Beginner', 'instructions' => 'Use a suitable assistance level, pull yourself upward smoothly, then lower without dropping.'],
        ['name' => 'Inverted Row', 'description' => 'A horizontal bodyweight pulling movement beneath a secure bar.', 'muscle_group' => 'Back, biceps', 'equipment' => 'Secure low bar or suspension trainer', 'difficulty' => 'Intermediate', 'instructions' => 'Keep your body straight beneath the bar, pull your chest toward it, then lower with control.'],
        ['name' => 'One-Arm Dumbbell Row', 'description' => 'A single-arm row to strengthen the upper back.', 'muscle_group' => 'Back, biceps', 'equipment' => 'Dumbbell and bench', 'difficulty' => 'Beginner', 'instructions' => 'Support one hand on a bench, row the dumbbell toward your hip, then lower and repeat on the other side.'],
        ['name' => 'Bent-Over Barbell Row', 'description' => 'A compound row performed with a hip-hinged torso.', 'muscle_group' => 'Back, biceps, rear shoulders', 'equipment' => 'Barbell', 'difficulty' => 'Intermediate', 'instructions' => 'Hinge with a neutral back, pull the bar toward your lower ribs, then lower it without changing your torso position.'],
        ['name' => 'Seated Cable Row', 'description' => 'A machine row emphasizing the middle back.', 'muscle_group' => 'Back, biceps', 'equipment' => 'Cable row machine', 'difficulty' => 'Beginner', 'instructions' => 'Sit tall, draw the handle toward your torso while squeezing your shoulder blades, then extend your arms slowly.'],
        ['name' => 'Lat Pulldown', 'description' => 'A machine-based vertical pulling exercise.', 'muscle_group' => 'Back, biceps', 'equipment' => 'Lat pulldown machine', 'difficulty' => 'Beginner', 'instructions' => 'Grip the bar wider than shoulder-width, pull it toward your upper chest, then let it rise under control.'],
        ['name' => 'Face Pull', 'description' => 'A cable or band pull that targets the rear shoulders and upper back.', 'muscle_group' => 'Rear shoulders, upper back', 'equipment' => 'Cable machine or resistance band', 'difficulty' => 'Beginner', 'instructions' => 'Pull the rope or band toward your face with elbows high, then return slowly while keeping shoulders down.'],
        ['name' => 'Dumbbell Shrug', 'description' => 'A simple shoulder elevation exercise for the upper back.', 'muscle_group' => 'Trapezius', 'equipment' => 'Dumbbells', 'difficulty' => 'Beginner', 'instructions' => 'Stand tall with weights at your sides, lift your shoulders toward your ears, then lower them slowly.'],
        ['name' => 'Plank', 'description' => 'An isometric core exercise performed on hands or forearms.', 'muscle_group' => 'Core, shoulders', 'equipment' => 'Bodyweight (home or gym)', 'difficulty' => 'Beginner', 'instructions' => 'Keep your body in a straight line, brace your abdomen, and hold while breathing steadily.'],
        ['name' => 'Side Plank', 'description' => 'An isometric core exercise emphasizing the sides of the trunk.', 'muscle_group' => 'Obliques, core', 'equipment' => 'Bodyweight (home or gym)', 'difficulty' => 'Intermediate', 'instructions' => 'Support yourself on one forearm and the side of your foot, lift your hips, and keep your body aligned.'],
        ['name' => 'Dead Bug', 'description' => 'A controlled floor exercise for core stability.', 'muscle_group' => 'Core, abdominals', 'equipment' => 'Bodyweight (home or gym)', 'difficulty' => 'Beginner', 'instructions' => 'Lie on your back with arms raised, slowly lower the opposite arm and leg while keeping your lower back steady, then alternate.'],
        ['name' => 'Bird Dog', 'description' => 'A quadruped exercise for core and back stability.', 'muscle_group' => 'Core, lower back, glutes', 'equipment' => 'Bodyweight (home or gym)', 'difficulty' => 'Beginner', 'instructions' => 'From hands and knees, extend the opposite arm and leg without twisting, return, and switch sides.'],
        ['name' => 'Bicycle Crunch', 'description' => 'A floor-based abdominal exercise with alternating rotation.', 'muscle_group' => 'Abdominals, obliques', 'equipment' => 'Bodyweight (home or gym)', 'difficulty' => 'Intermediate', 'instructions' => 'Lie on your back and alternate bringing each elbow toward the opposite knee with controlled movement.'],
        ['name' => 'Crunch', 'description' => 'A basic abdominal curl performed on the floor.', 'muscle_group' => 'Abdominals', 'equipment' => 'Bodyweight (home or gym)', 'difficulty' => 'Beginner', 'instructions' => 'Lie on your back with knees bent, gently curl your shoulders off the floor, then lower slowly.'],
        ['name' => 'Leg Raise', 'description' => 'A floor exercise for the lower abdominals and hip flexors.', 'muscle_group' => 'Abdominals, hip flexors', 'equipment' => 'Bodyweight (home or gym)', 'difficulty' => 'Intermediate', 'instructions' => 'Lie on your back, brace your abdomen, raise your legs without arching your back, then lower with control.'],
        ['name' => 'Mountain Climber', 'description' => 'A dynamic bodyweight exercise combining core stability and cardio.', 'muscle_group' => 'Core, shoulders, hip flexors', 'equipment' => 'Bodyweight (home or gym)', 'difficulty' => 'Intermediate', 'instructions' => 'Start in a strong plank and alternate bringing your knees toward your chest at a controlled pace.'],
        ['name' => 'Russian Twist', 'description' => 'A seated rotational core exercise.', 'muscle_group' => 'Obliques, core', 'equipment' => 'Bodyweight or optional weight', 'difficulty' => 'Intermediate', 'instructions' => 'Sit with knees bent, lean back slightly, brace your core, and rotate your torso gently from side to side.'],
        ['name' => 'Jumping Jack', 'description' => 'A full-body cardio warm-up movement.', 'muscle_group' => 'Full body, cardiovascular', 'equipment' => 'Bodyweight (home or gym)', 'difficulty' => 'Beginner', 'instructions' => 'Jump your feet apart as your arms rise overhead, then return to the starting stance at a comfortable pace.'],
        ['name' => 'High Knees', 'description' => 'A running-in-place cardio drill.', 'muscle_group' => 'Legs, core, cardiovascular', 'equipment' => 'Bodyweight (home or gym)', 'difficulty' => 'Intermediate', 'instructions' => 'Jog in place, lifting your knees comfortably while pumping your arms and maintaining an upright posture.'],
        ['name' => 'Burpee', 'description' => 'A full-body conditioning movement combining a squat and plank transition.', 'muscle_group' => 'Full body, cardiovascular', 'equipment' => 'Bodyweight (home or gym)', 'difficulty' => 'Advanced', 'instructions' => 'Squat, place hands down, step or jump to a plank, return to a squat, then stand; add a jump only if comfortable.'],
        ['name' => 'Bodyweight Good Morning', 'description' => 'A hip-hinge drill for learning posterior-chain movement.', 'muscle_group' => 'Hamstrings, glutes, lower back', 'equipment' => 'Bodyweight (home or gym)', 'difficulty' => 'Beginner', 'instructions' => 'Place hands across your chest, soften your knees, hinge hips back with a neutral spine, then stand tall.'],
        ['name' => 'Resistance Band Row', 'description' => 'A home-friendly horizontal pull using an anchored resistance band.', 'muscle_group' => 'Back, biceps', 'equipment' => 'Resistance band and secure anchor', 'difficulty' => 'Beginner', 'instructions' => 'With the band securely anchored, pull your hands toward your ribs, squeeze your shoulder blades, then return slowly.'],
        ['name' => 'Resistance Band Chest Press', 'description' => 'A horizontal pressing exercise using a resistance band.', 'muscle_group' => 'Chest, shoulders, triceps', 'equipment' => 'Resistance band and secure anchor', 'difficulty' => 'Beginner', 'instructions' => 'Anchor the band behind you, press your hands forward from chest height, then return under control.'],
        ['name' => 'Resistance Band Pull-Apart', 'description' => 'An upper-back exercise using a light resistance band.', 'muscle_group' => 'Upper back, rear shoulders', 'equipment' => 'Resistance band', 'difficulty' => 'Beginner', 'instructions' => 'Hold a band at chest height with straight arms, pull it apart by squeezing your shoulder blades, then return slowly.'],
        ['name' => 'Kettlebell Swing', 'description' => 'A hip-hinge conditioning exercise using a kettlebell.', 'muscle_group' => 'Glutes, hamstrings, core', 'equipment' => 'Kettlebell', 'difficulty' => 'Advanced', 'instructions' => 'Hinge at the hips and guide the kettlebell between your legs, then drive your hips forward to swing it to chest height.'],
        ['name' => 'Dumbbell Chest-Supported Row', 'description' => 'A back row performed with the chest supported on an incline bench.', 'muscle_group' => 'Back, biceps', 'equipment' => 'Dumbbells and incline bench', 'difficulty' => 'Beginner', 'instructions' => 'Lie chest-down on an incline bench, row dumbbells toward your hips, then lower without lifting your chest.'],
        ['name' => 'Cable Biceps Curl', 'description' => 'A cable-based curl with steady resistance.', 'muscle_group' => 'Biceps', 'equipment' => 'Cable machine and handle', 'difficulty' => 'Beginner', 'instructions' => 'Keep elbows close to your sides, curl the handle upward, then return slowly without leaning back.'],
        ['name' => 'Machine Chest Press', 'description' => 'A guided machine press for the chest and arms.', 'muscle_group' => 'Chest, shoulders, triceps', 'equipment' => 'Chest press machine', 'difficulty' => 'Beginner', 'instructions' => 'Adjust the seat so handles align with your chest, press forward smoothly, then return until you feel a comfortable stretch.'],
        ['name' => 'Machine Shoulder Press', 'description' => 'A guided overhead press for the shoulders.', 'muscle_group' => 'Shoulders, triceps', 'equipment' => 'Shoulder press machine', 'difficulty' => 'Beginner', 'instructions' => 'Adjust the seat so handles start near shoulder level, press upward smoothly, then lower with control.'],
        ['name' => 'Hip Thrust', 'description' => 'A glute-focused hip extension exercise using a bench.', 'muscle_group' => 'Glutes, hamstrings', 'equipment' => 'Bench; optional barbell or dumbbell', 'difficulty' => 'Intermediate', 'instructions' => 'Rest your upper back on a stable bench, drive through your feet to extend your hips, then lower slowly.'],
        ['name' => 'Treadmill Walk', 'description' => 'A low-impact cardio option with adjustable speed and incline.', 'muscle_group' => 'Legs, cardiovascular', 'equipment' => 'Treadmill', 'difficulty' => 'Beginner', 'instructions' => 'Choose a comfortable pace, walk with an upright posture, and use the safety clip when available.'],
        ['name' => 'Stationary Bike', 'description' => 'A low-impact cardiovascular exercise on a stationary bicycle.', 'muscle_group' => 'Legs, cardiovascular', 'equipment' => 'Stationary bike', 'difficulty' => 'Beginner', 'instructions' => 'Adjust the seat so your knee remains slightly bent at the bottom of each pedal stroke, then cycle comfortably.'],
        ['name' => 'Elliptical Trainer', 'description' => 'A low-impact cardio workout using an elliptical machine.', 'muscle_group' => 'Full body, cardiovascular', 'equipment' => 'Elliptical machine', 'difficulty' => 'Beginner', 'instructions' => 'Stand upright, hold the handles as desired, and move the pedals smoothly at a sustainable pace.'],
        ['name' => 'Rowing Machine', 'description' => 'A full-body cardio exercise using a rowing ergometer.', 'muscle_group' => 'Legs, back, arms, cardiovascular', 'equipment' => 'Rowing machine', 'difficulty' => 'Beginner', 'instructions' => 'Drive with your legs first, lean back slightly, then draw the handle toward your torso; reverse the sequence to return.'],
    ];

    public function run(): void
    {
        $now = now();
        $bestForByExercise = [];

        foreach (self::BEST_FOR as $goals => $exerciseNames) {
            foreach ($exerciseNames as $exerciseName) {
                $bestForByExercise[$exerciseName] = $goals;
            }
        }

        foreach (self::EXERCISES as $exercise) {
            if (! isset($bestForByExercise[$exercise['name']])) {
                throw new \LogicException("No fitness goals configured for exercise: {$exercise['name']}");
            }

            DB::table('exercises')->updateOrInsert(
                ['name' => $exercise['name']],
                [
                    ...$exercise,
                    'created_by' => null,
                    'best_for' => $bestForByExercise[$exercise['name']],
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );
        }
    }
}
