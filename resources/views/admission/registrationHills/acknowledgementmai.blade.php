<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <title>Hills' High School – Parent Information</title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <style>
    :root {
      font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI",
        Roboto, sans-serif;
      color-scheme: light;
    }

    body {
      margin: 0;
      padding: 0;
      background: #f3f4f6;
      color: #111827;
    }

    .page {
      max-width: 1000px;
      margin: 0 auto;
      padding: 24px 16px 40px;
    }

    .hero-card {
  background: #ffffff; /* PURE WHITE BACKGROUND */
  border-radius: 18px;
  padding: 32px 28px 38px;
  border: 2px solid #e5e7eb; /* soft grey border */
  box-shadow: 0 12px 30px rgba(0, 0, 0, 0.06);
  margin-bottom: 24px;
}

/* Add colorful top border inspired by the logo */
.hero-card::before {
  content: "";
  display: block;
  height: 6px;
  width: 100%;
  border-radius: 18px 18px 0 0;
  background: linear-gradient(
    90deg,
    #0052A3,  /* Blue */
    #E53935,  /* Red */
    #F57C00,  /* Orange */
    #43A047,  /* Green */
    #FDD835   /* Yellow */
  );
}

    .hero-card h1 {
      margin: 0 0 8px;
      font-size: 1.9rem;
      letter-spacing: 0.03em;
      text-transform: uppercase;
    }

    .hero-subtitle {
      font-size: 0.98rem;
      opacity: 0.95;
      max-width: 720px;
      line-height: 1.6;
    }

    .hero-meta {
      display: flex;
      flex-wrap: wrap;
      gap: 12px;
      margin-top: 18px;
      font-size: 0.9rem;
      opacity: 0.98;
    }

    .badge {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      padding: 4px 10px;
      border-radius: 999px;
      background: rgba(15, 23, 42, 0.2);
      border: 1px solid rgba(240, 253, 250, 0.35);
      font-size: 0.8rem;
      text-transform: uppercase;
      letter-spacing: 0.06em;
    }

    .badge-dot {
      width: 7px;
      height: 7px;
      border-radius: 999px;
      background: #a7f3d0;
    }

    .cards-grid {
      display: grid;
      grid-template-columns: minmax(0, 2fr) minmax(0, 1.6fr);
      gap: 18px;
      margin-bottom: 24px;
    }

    @media (max-width: 800px) {
      .cards-grid {
        grid-template-columns: minmax(0, 1fr);
      }
    }

    .card {
      background: #ffffff;
      border-radius: 16px;
      padding: 18px 20px 20px;
      box-shadow: 0 10px 25px rgba(15, 23, 42, 0.08);
      margin-bottom: 18px;
      border: 1px solid rgba(148, 163, 184, 0.35);
    }

    .card h2 {
      margin-top: 0;
      margin-bottom: 10px;
      font-size: 1.2rem;
      text-transform: uppercase;
      letter-spacing: 0.08em;
      color: #0f172a;
    }

    .card h3 {
      margin-top: 18px;
      margin-bottom: 8px;
      font-size: 1.02rem;
      text-transform: uppercase;
      letter-spacing: 0.08em;
      color: #111827;
    }

    .card p {
      margin: 6px 0;
      line-height: 1.6;
      font-size: 0.96rem;
    }

    .card ul {
      margin: 8px 0 10px 1.2rem;
      padding-left: 0.8rem;
      font-size: 0.95rem;
      line-height: 1.6;
    }

    .card li {
      margin: 4px 0;
    }

    .muted {
      color: #6b7280;
      font-size: 0.9rem;
    }

    .pill-section-title {
      font-size: 0.75rem;
      text-transform: uppercase;
      letter-spacing: 0.18em;
      color: #6b7280;
      margin: 30px 2px 6px;
    }

    .houses-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
      gap: 14px;
    }

    .house-card {
      border-radius: 14px;
      padding: 12px 13px 12px;
      background: #f9fafb;
      border: 1px solid rgba(148, 163, 184, 0.5);
      font-size: 0.94rem;
    }

    .house-badge {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      font-size: 0.85rem;
      letter-spacing: 0.08em;
      text-transform: uppercase;
      margin-bottom: 6px;
      font-weight: 600;
    }

    .house-dot {
      width: 10px;
      height: 10px;
      border-radius: 999px;
    }

    .house-aravalli .house-dot {
      background: #ea580c;
    }

    .house-himalaya .house-dot {
      background: #2563eb;
    }

    .house-sahyadri .house-dot {
      background: #16a34a;
    }

    .house-vindhya .house-dot {
      background: #dc2626;
    }

    .section-divider {
      height: 1px;
      background: linear-gradient(
        to right,
        rgba(148, 163, 184, 0.4),
        rgba(209, 213, 219, 0.1)
      );
      margin: 24px 0;
    }

    .two-col-text {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
      gap: 16px;
    }

    .tag-line {
      font-size: 0.86rem;
      letter-spacing: 0.16em;
      text-transform: uppercase;
      color: #e0f2fe;
      margin-bottom: 4px;
      opacity: 0.95;
    }

    a {
      color: #2563eb;
      text-decoration: none;
    }

    a:hover {
      text-decoration: underline;
    }

    .footer-note {
      text-align: center;
      margin-top: 28px;
      font-size: 0.95rem;
      color: #4b5563;
    }
  </style>
</head>
<body>
  <div class="page">
    <!-- HERO / INTRO -->
    <section class="hero-card">
      <div>Welcome to Hills' High School</div>
      <h1>Information for Parents</h1>
      <p class="hero-subtitle">
        Dear Parents<br />
        We are delighted to welcome you and your child to Hills' High School.
        As we begin this new academic year, we would like to introduce you to
        our school's vibrant community, rich traditions, and exciting
        opportunities. Our school offers a diverse range of clubs, a house
        system, and various activities designed to foster growth, creativity,
        and teamwork.
      </p>
      <p class="hero-subtitle">
        We would also like to share our school's rules and regulations with
        you. These guidelines are designed to create a safe, supportive, and
        inclusive environment where your child can thrive. Think of these
        policies as a roadmap to help us work together to ensure your child's
        success and happiness.
      </p>
      <p class="hero-subtitle">
        Please take a moment to review them and don't hesitate to reach out to
        us if you have any questions or concerns via email -
        <strong>admission@hillshigh.com</strong>
      </p>
      <p class="hero-subtitle">
        We look forward to productive and enjoyable years ahead!!
      </p>

      <div class="hero-meta">
        <span class="badge">
          <span class="badge-dot"></span>
          Hills' High School  CBSE
        </span>
        <span class="badge">
          <span class="badge-dot"></span>
          Parents  Students  Community
        </span>
      </div>
    </section>

    <!-- HOUSE SYSTEM -->
    <div class="pill-section-title">House System</div>
    <section class="card">
      <h2>HOUSE SYSTEM OF HILLS HIGH SCHOOL</h2>

      <div class="houses-grid">
        <div class="house-card house-aravalli">
          <div class="house-badge">
            <span class="house-dot"></span> ARAVALLI HOUSE
          </div>
          <p>
            "Aravalli" the name of the mountain range of western India with its
            orange colour signifies strength, energy and vitality. It is indeed
            the colour of life itself and the colour of the sun which is even
            higher than the Aravalli itself.
          </p>
        </div>

        <div class="house-card house-himalaya">
          <div class="house-badge">
            <span class="house-dot"></span> HIMALAYA HOUSE
          </div>
          <p>
            "Himalaya" House, the name of the colossal Himalayas, the world's
            tallest mountains, with its blue colour inspires its house members to
            always aim high but maintain the cool and the equilibrium required to
            do so, signified by the colour blue.
          </p>
        </div>

        <div class="house-card house-sahyadri">
          <div class="house-badge">
            <span class="house-dot"></span> SAHYADRI HOUSE
          </div>
          <p>
            "Sahyadri" House, named after the Western Ghats teaches it's house
            members to be tough and strong, at the same time work hard towards
            success, prosperity and a beautiful life signified by the colour
            green.
          </p>
        </div>

        <div class="house-card house-vindhya">
          <div class="house-badge">
            <span class="house-dot"></span> VINDHYA HOUSE
          </div>
          <p>
            "Vindhya" House, named after the hills in the west central Indian
            sub-continent motivates its house members to be infallible and
            strong. Its red colour symbolizes vigour, strong leadership and a
            vibrancy equalled to none.
          </p>
        </div>
      </div>

      <p style="margin-top: 10px;">
        Thus, all the Houses with their House Masters, Captains and Vice-Captain
        aim to achieve the highest possible limits of discipline, honesty and an
        all-round development of each and every individual of the school.
      </p>
    </section>

    <div class="section-divider"></div>

    <!-- ETIQUETTES & APPEAL -->
    <div class="cards-grid">
      <section class="card">
        <h2>ETIQUETTES EXPECTED FROM A HILLS' HIGH STUDENT</h2>
        <ul>
          <li>
            Greeting young and old alike with a warm 'Hello' How are you ? or
            Good morning according to the eye and position of the individual.
          </li>
          <li>Using the washroom cleanly without wasting water.</li>
          <li> Helping a junior board or alight from the van.</li>
          <li> Talking softly while others are studying.</li>
          <li> Using a tissue or handkerchief while sneezing.</li>
          <li>
             Covering mouth while coughing, yawning or sneezing.
          </li>
        </ul>
      </section>

      <section class="card">
        <h2>AN APPEAL TO PARENTS :</h2>
        <p class="muted">
          It is an established fact that education begins at home, we expect full
          co-operation between the home and the school for providing the best
          learning ambience in the school. Parents are requested to
        </p>
        <ul>
          <li>
            To respond to the invitations of the school for attending school
            events, celebrations and interhouse competitions.
          </li>
          <li>
             To see the record of Home works, Activity files and report cards
            are signed after checking the work.
          </li>
          <li>
             To discourage their wards from bringing polythene carry bags to
            school as plastic is hazardous to health and environment.
          </li>
          <li>
            To ensure that their wards do not bring mobile phones and other
            electronic gadgets. If found the same will be confiscated.
          </li>
          <li>
             To ensure that parents are always dressed decently when they come
            to school. No night wear or shorts are permitted in the premises.
          </li>
        </ul>
      </section>
    </div>

    <div class="section-divider"></div>

    <!-- COUNSELLING -->
    <section class="card">
      <h2>COUNSELLING - A PROGRAM WITH A DIFFERENCE</h2>
      <p>
        Counselling is a process where a trained professional, known as a
        counsellor, provides support and guidance to individuals or groups
        facing challenges, difficulties, or seeking personal development. It
        involves confidential conversations to explore emotions, thoughts, and
        behaviours, with the goal of helping the person(s) gain insights, cope
        with issues, and make positive changes in their lives. Counselling may
        address a wide range of concerns, including mental health,
        relationships, stress, and personal growth.
      </p>
      <p>
        The National Education Policy (NEP), 2020 recognises that mental health
        is integral to the broader vision of education which is reflected in the
        focus on socio-emotional aspects of development as an important
        prerequisite for optimal learning across stages of education.
      </p>
      <p>
        As per CBSE bye-law 2.4.12; our school has full-time professionals as
        Counsellors and Wellness Teacher. CBSE provides psychological counselling
        to the X and XII examinees and differently abled students. These
        services can be availed through official CBSE website. A comprehensive
        audio-visual presentation titled 'Knowing your child Better' is
        available on CBSE website.
      </p>
      <p>
        <a
          href="https://www.cbse.gov.in/cbsenew/prunit_temp/index.html"
          target="_blank"
          rel="noopener noreferrer"
        >
          https://www.cbse.gov.in/cbsenew/prunit_temp/index.html
        </a>
      </p>

      <h3>KNOWING CHILDREN BETTER</h3>
      <p>
        When the child's behaviour does not fit with what is appropriate for the
        child's developmental level, and is frequent or extreme, it is important
        to try to discover the reasons for the behaviour.
      </p>
      <p>Challenging behaviours to identify</p>
      <ul>
        <li> The child causes harm /risk to self or others.</li>
        <li>
           There is interference with the child's learning and relationship
          with others.
        </li>
        <li>
           The child is extremely shy, withdrawn or excessively passive.
        </li>
        <li>
           Inappropriate age-behavioural or developmental delay.
        </li>
      </ul>

      <h3>Improving parent-child relationship</h3>
      <ul>
        <li>
           Show your love , greet them with smile and encourage honest
          interaction.
        </li>
        <li>
           Listen and empathise - Connection starts with listening. Acknowledge
          your child's feelings, show them you understand and reassure them that
          you are there to help with whatever they need.
        </li>
        <li> Be available and distraction-free.</li>
        <li> Spend quality time with your child</li>
        <li> Listen to the whole story without jumping to conclusions.</li>
        <li>Know your child's friends.</li>
        <li>
           Parents can refer to a book provided by CBSE; “Positive Parenting: A
          Ready Reckoner”. The book is available on CBSE website.
        </li>
      </ul>

      <h3>Behaviour guidance</h3>
      <p>
        There may be times when additional professional assistance and external
        support are needed to help a child. Effective partnership with the
        School Counsellor would work well for the child.
      </p>
      <p>How to connect to the school counsellor</p>
      <ul>
        <li> Through a diary note.</li>
        <li> A request may be sent via school application.</li>
        <li>
           CAREER COUNSELLING FOR STD IX TO XII is available at school
          premises.
        </li>
      </ul>
    </section>

    <div class="section-divider"></div>

    <!-- SUGAR AWARENESS -->
    <section class="card">
      <h2>
        IMPLEMENTATION OF SUGAR AWARENESS INITIATIVE - SUGAR-FREE AND MEANINGFUL
        BIRTHDAY CELEBRATIONS
      </h2>

      <h3>Sugar-Free &amp; Meaningful Birthday for Students</h3>
      <p>
        In line with the recent CBSE directive to promote awareness about the
        harmful effects of excessive sugar consumption among children, Hills
        High School has initiated steps to create a healthier and more mindful
        school environment. As part of this effort, we are introducing a
        “Sugar-Free and Meaningful Birthday Celebration  an alternative to the
        traditional practice of distributing chocolates and sugary snacks. This
        initiative aims to support the health and well-being of our students by
        reducing sugar intake, while also encouraging thoughtful and value-based
        celebrations. Through this, we hope to instill healthy habits and
        promote positive choices that contribute to the overall development of
        our learners.
      </p>
      <p>Let's promote joyful and nutritious birthday celebration!</p>

      <div class="two-col-text">
        <div>
          <h3>In School - Treats</h3>
          <p>
            Students can share healthy treats like dates, dry fruits, fresh
            fruits (without requirement of knives eg-banana, grapes, orange),
            fruit salad or jaggery based sweets.
          </p>
        </div>
        <div>
          <h3>At Home</h3>
          <p>
            Celebrate with homemade jaggery sweets instead of cake, and enjoy
            traditional snacks with a modern, healthy twist. Teachers will plan
            entertaining activities for the students as a celebration.
          </p>
          <p><strong>Health is wealth !!</strong></p>
        </div>
      </div>
    </section>

    <div class="section-divider"></div>

    <!-- GENERAL RULES -->
    <section class="card">
      <h2>GENERAL RULES</h2>
      <ul>
        <li> Park all the vehicles systematically.</li>
        <li> Do not send sick children to school.</li>
        <li>
           Do not go directly to the classes to meet any teacher. For urgent
          matters, please meet supervisors at the office Reception.
        </li>
        <li>
           Please inform class teacher, office staff and supervisor regarding
          any changes in phone number, address, name of van uncle etc.
        </li>
        <li>
           Please send nutritious nashta for your child with a water bottle,
          napkin &amp; spoon.
        </li>
        <li>
           Please label all the articles like water bottle, nashta box, school
          bag, shoes, sweater etc.
        </li>
        <li>
           Parents may meet their class teachers only on P.T.M. In case a
          special appointment is needed in between, please take an appointment
          via the school app.
        </li>
        <li> All meetings with prior appointment only from the school office.</li>
        <li>
           No Valuable items must be brought to school. School will not be
          responsible for any such loss. Unauthorised objects / materials are
          STRICTLY PROHIBITED
        </li>
        <li> Any damage to school property will have to be compensated.</li>
        <li> No one should scratch, write, paste or draw on the desk or wall.</li>
        <li>
           Damage caused even by accident should be reported to the class
          teacher.
        </li>
        <li>
           The medium of communication inside the school campus should be
          English only.
        </li>
      </ul>
    </section>

    <div class="section-divider"></div>

    <!-- LEAVE RULES -->
    <section class="card">
      <h2>LEAVE RULES</h2>
      <ul>
        <li>
           No student shall absent her/himself from school on any days without
          the written permission of the class teacher and principal.
        </li>
        <li>
           A written request on the school app showing the valid reason must be
          made in advance by the parents for permitting a leave.
        </li>
        <li>
           Sick students especially those who suffer from infectious diseases
          should not attend school After recovery they should produce a fitness
          certificate from a medical officer.
        </li>
        <li>
           85% attendance is compulsory for promotion to the next class except
          for medical reasons.
        </li>
        <li>
           Average marks/grade will be awarded to any student in the
          assessments, who would be absent on medical grounds. (Medical
          Certificate is MANDATORY)
        </li>
        <li>
           It is compulsory for the school to upload daily attendance of
          students on DEO's website. Hence it is compulsory for all children to
          attend the school full day. Half day leave is not granted.
        </li>
        <li>
           All students must come to the school on time. The late comers will
          not be entertained.
        </li>
        <li>
           In case a student IRREGULARITY record shows more than 3 lates in a
          month, the purple/yellow string will be confiscated and it will be
          COMPULSORY for parents to accompany their ward.
        </li>
        <li>
           The names of those students who remain absent without leave note for
          more than twenty days may be struck off from the class register
        </li>
      </ul>
    </section>

    <!-- TRANSFER CERTIFICATE RULES -->
    <section class="card">
      <h2>TRANSFER CERTIFICATE RULES :</h2>
      <ul>
        <li> One month's notice must be given in writing for a withdrawal.</li>
        <li>
           Application for the TC should be submitted in the prescribed format
          available in the school office.
        </li>
        <li>
           A student will be issued TC only when the school dues have been
          cleared.
        </li>
        <li>
           Application must be submitted latest by 15th January or else you are
          liable to pay next terms fee.
        </li>
        <li>
           Application for TC will not be accepted for any cases after 31st
          August for the Current Year.
        </li>
      </ul>
    </section>

    <!-- VAN RULES -->
    <section class="card">
      <h2>VAN RULES</h2>
      <ul>
        <li>
           Please keep your child ready with an adult at the time of pickup and
          dropping. The Van uncle will not wait.
        </li>
        <li> Van fees should be paid to the van uncle at the end of every month.</li>
        <li>
           School will not be responsible for any loan or advance payments given
          to the van uncle.
        </li>
        <li> Please tell your child to maintain discipline in the van also.</li>
        <li>
           Van will not be allowed to enter the school after 7.25 am / 12.40
          The students will be sent back home.
        </li>
      </ul>
    </section>

    <div class="section-divider"></div>

    <!-- SCHOOL APP -->
    <section class="card">
      <h2>SCHOOL APP</h2>
      <p>
        All major communication will take place through the school app only.
        Kindly familiarize yourself with the school app. Please note the various
        facilities available under each MENU.
      </p>
      <p> Activity Menu:</p>
      <ul>
        <li>(a) Exam schedule</li>
        <li>(b) Students attendance</li>
        <li>(c) Circular and events</li>
        <li>(d) Academic calendar</li>
        <li>(e) Result</li>
      </ul>
      <p> Profile Gallery Menu:</p>
      <ul>
        <li>(a) Photo Gallery</li>
        <li>(b) Video Gallery.</li>
      </ul>
      <p>Main menu:</p>
      <ul>
        <li>(a) Notification</li>
        <li>(b) Homework</li>
        <li>(c) Fees details (Amt paid / Amt outstanding / Receipts)</li>
      </ul>
      <p>Utility menu:</p>
      <ul>
        <li>(a) Leave Application</li>
        <li>(b) Parent Communication</li>
        <li>(c) Holiday list</li>
        <li>(d) Time Table</li>
      </ul>
      <p>
         School Details menu you can view all the other school related
        information.
      </p>
      <p> Student Details Personal Details of your ward's.</p>
      <ul>
        <li>(a) Transport</li>
        <li>(b) Infirmary</li>
        <li>(c) Height and Weight</li>
      </ul>
      <p> Pending fess details is available on school app.</p>
    </section>

    <!-- DIARY -->
    <section class="card">
      <h2>DIARY</h2>
      <ul>
        <li> It is COMPULSORY for students to carry their diary to school EVERYDAY.</li>
        <li> Please fill up the page 1 &amp; 2 of the diary.</li>
        <li> Please refer to the diary and sign it regularly.</li>
        <li>
           Please send a message through the school app in case you don't
          receive the diary or if your child has lost it.
        </li>
        <li>
           In case of any changes in your contact details, immediately inform
          the school office.
        </li>
        <li> Keep the diary neat, clean and intact.</li>
      </ul>
    </section>

    <!-- PARENT I CARD -->
    <section class="card">
      <h2>PARENT I CARD</h2>
      <p>
        Parents are provided with a parent I Card, which must be presented at
        the school to:
      </p>
      <ul>
        <li>
           Enter the school premises (entry will not be allowed to parents in
          night wear / shorts / Chewing Tobaco)
        </li>
        <li>
           Pick up the child from school (if the I card is lost immediately
          inform the school)
        </li>
        <li> Meet the Management/ Principal / Supervisor / Teacher</li>
        <li> Process any administrative or transport related work.</li>
        <li> Not to be shared with students</li>
        <li> For School PTM carrying Parent I Card is compulsory.</li>
      </ul>
    </section>

    <!-- STUDENT I CARD -->
    <section class="card">
      <h2>STUDENT I CARD</h2>
      <ul>
        <li> It is compulsory for students to wear their I card.</li>
        <li>
           Any student found not abiding this rule will be marked ABSENT for
          that day. After 3 warnings strict action will be taken.
        </li>
        <li>
           The I-Card must have updated and correct details at all times (THIS
          IS A NECCESITY IN TIMES OF EMERGENCY)
        </li>
      </ul>
    </section>

    <!-- PTM GUIDELINES -->
    <section class="card">
      <h2>PTM GUIDELINES</h2>
      <ul>
        <li> Entry to parents ONLY ON presentation of the PARENT I-CARD.</li>
        <li> Make sure your child is attending the PTM along with you.</li>
        <li> SCHOOL UNIFORM is compulsory for your child on the day of the PTM.</li>
        <li>
           It is compulsory for parents to STRICTLY follow the time schedule
          given (Teachers will not be present in school before or after the time
          specified)
        </li>
      </ul>
    </section>

    <!-- CHANGE IN ADMIN DETAILS -->
    <section class="card">
      <h2>PROCEDURE FOR CHANGE IN ADMINISTRATIVE DETAILS</h2>
      <p>
         Updated contact details must be on record to avoid serious security &
        safety lapse at times of emergency.
      </p>
      <p>Procedure for change in contact details</p>
      <ul>
        <li>
           Parents need to visit the school and submit an application in the
          prescribed format along with the charges.
        </li>
        <li>
           No charges will be taken if a parent informs the school before the
          commencement of the new session. (Before 15th January)
        </li>
        <li>
           If it is found that the phone numbers have not been updated during
          the current year, the incorrect I card will be confiscated with a
          fine. (hence the child will not be able to attend school)
        </li>
        <li> Transfer from afternoon shift to morning is not admissible.</li>
      </ul>
    </section>

    <div class="section-divider"></div>

    <!-- FEES RULES -->
    <section class="card">
      <h2>PROCEDURE &amp; RULES FOR SCHOOL FEES</h2>
      <ul>
        <li>
           As per the CBSE Circular - CBSE /SECY/SPS/2016 dated Dec-10,2016 it
          was compulsory, for all schools to introduce digital and cashless ways
          of online fee collection.
        </li>
        <li> It is COMPULSORY to pay fee through NACH debit mandate.</li>
        <li> The fees will be deducted on a 'MONTHLY' basis.</li>
        <li>
           Parents are requested to regularly check the fee section on the
          Hills High School App for the outstanding amount &amp; to download fee
          receipts.
        </li>
        <li>
           Incase in change of ECS for NACH Mandate charges are applicable.
        </li>
        <li> Fees for year will be deducted on the 7th of every month.</li>
        <li> Pending fee details is available on the school app.</li>
        <li>
           Reminder will be given approximately a week in advance via
          notification in the school app.
        </li>
        <li>
           Outstanding dues of the previous fees if any will be deducted on 20th
          of every month.
        </li>
        <li>
           Please keep sufficient balance to avoid any inconvenience.(Your bank
          may levy charges )
        </li>
        <li> Outstanding fees of more than 2 months will be dealt with strictly.</li>
        <li>
           In case the fees of a particular session (Term/Year) remain unpaid,
          the school will not be able to resume the child's schooling for the
          upcoming session.
        </li>
        <li> Monthly late fee will be automatically charged on default.</li>
      </ul>
    </section>

    <!-- UNIFORM -->
    <section class="card">
      <h2>UNIFORM</h2>
      <ul>
        <li> All students must be neatly dressed.</li>
        <li>
           Send your child in proper school uniforms including P.T. uniform to
          be worn on specified days.
        </li>
        <li>
           No jewellery must be worn to school. Only simple earings (studs)
          permitted for girls.
        </li>
        <li>
           Students must wear the I-Card with the correct and recent phone
          numbers including van uncle's phone nos.
        </li>
        <li>
           Nail Polish, Kajal, tattoos, makeup, hair colour, highlights is
          strictly prohibited. Any child found with the same will not be
          ALLOWED TO ATTEND SCHOOL, TILL THE SAME IS REMOVED.
        </li>
        <li> Boys and girls hair must be properly OILED.</li>
        <li>
           A small dot of mehendi may ONLY be applied on the palm of the hand.
        </li>
        <li>
           Boys' hair must be short. No fancy hair styles will be permitted.
          Any student with such fancy hair styles will NOT BE ALLOWED TO ATTEND
          SCHOOL TILL THE HAIRSTYLE IS RECTIFIED.
        </li>
        <li>
           Girls' hair must be cut short , or tied in 2 plaits with BLACK
          RUBBER BANDS.
        </li>
        <li> All students to be dressed in their school uniforms on the days of P.T.M.</li>
        <li>
           It is compulsory for students to wear lab coat / apron during
          practical periods at Higher Secondary level otherwise students will
          not be allowed to conduct the practical.
        </li>
        <li>
           School uniform promotes discipline and a sense of equality among
          students. It also helps to build right spirit among the students and
          instills a feeling of belongingness. It is compulsory for all students
          to follow the school dress code.
        </li>
      </ul>

      <h3>SUMMER UNIFORM</h3>
      <p>BOYS GIRLS</p>
      <p>1. Shirt 1. Shirt</p>
      <p>
        2. Shorts (Std. 1-5) <br />
        Full Pant (Std. 6-12)
      </p>
      <p>2. Skirt (Std. 1-12)</p>
      <p>3. Belt 3. Black cycle shorts</p>
      <p>4. Vest 4. Slip</p>
      <p>
        Please Note : Sikh students must use a dark blue material for their
        turban and From April to
      </p>
      <p>October students are not required to wear the tie.</p>

      <h3>WINTER UNIFORM</h3>
      <p>All of the above in addition to ......</p>
      <p>BOYS GIRLS</p>
      <p>1. Full Pant (Std. 1-12) 1. Full length black leggings (Std. 1-12)</p>
      <p>2. School Sweater 2. School Sweater</p>
      <p>3. Tie (Std. 6 - 12) 3. Tie (Std. 6 - 12)</p>
      <p>
        Please Note : Only SHORT-SLEEVED INNER OR THERMAL WEAR will be
        permitted* .
      </p>
    </section>

    <!-- BAGS -->
    <section class="card">
      <h2>BAGS</h2>
      <ul>
        <li>
           It is compulsory to use ONLY the Hills High School bag as it is a
          part of school uniform.
        </li>
        <li>
           On days allotted for Drawing and Craft,the required materials must
          be packed in the Drawing bag. (Std 1 - 10)
        </li>
        <li>
           There is a total ban on use of plastic bags. We recommend use of
          CLOTH BAGS in case required.
        </li>
        <li>
           Please do not send heavy water bottles and nashta boxes.
        </li>
        <li>
           Ensure the student carries a light weight compass box with limited
          stationery items.
        </li>
        <li>
           Kindly follow teachers instructions for packing Note Book (N.B.) and
          Text Book (T.B.) to avoid heavy bags. (Std. 1 and 2)
        </li>
        <li>
           You may bind the textbooks in 2 parts as per the syllabus to avoid
          carrying heavy weight.
        </li>
        <li> Please label all belongings.</li>
      </ul>
    </section>

    <div class="section-divider"></div>

    <!-- CANTEEN APP -->
    <section class="card">
      <h2>HILLS HIGH CANTEEN APP</h2>
      <p>
        In order to serve the students with nutritious and fresh snacks during
        the school in a better and more efficient manner, the school has
        launched the “Hills Canteen App”.
      </p>
      <p>Please note the following important instructions:-</p>
      <ul>
        <li>
           In order to avail canteen facility, orders have to be placed 1 day
          in advance.
        </li>
        <li>
           Snacks will be ordered STRICTLY through the app ONLY (no cash will
          be accepted)
        </li>
        <li>
           Only 1 plate per item can be ordered per child per day.
        </li>
        <li>
           Students will not be permitted to purchase canteen snacks on demand
          to avoid them for carrying money to school.
        </li>
        <li> Order has to be placed before 7am of the same day.</li>
        <li>
           Order may be placed for 1 day, one week or 1 month well in advance.
        </li>
        <li>
           Mention the details of your Ward-Name, Class, Section while
          ordering.
        </li>
        <li>
           Inform your ward to collect the ordered snacks from the designated
          Place in the school.
        </li>
        <li>
           In order to avoid use of disposable plates and spoons, as part of
          our “Environment Friendly” policy, please send an empty nashta box for
          the students to be served in.
        </li>
      </ul>
    </section>

    <!-- LOST & FOUND -->
    <section class="card">
      <h2>LOST &amp; FOUND</h2>
      <ul>
        <li>
           Please label all the articles which are sent to the school like
          bags, water bottles, shoes, shirt, pant, sweater, skates etc.......
        </li>
        <li>
           In case of any loss please inform through the school app within one
          day, so that we can help you productively.
        </li>
        <li> Please send a Diary Note the next day.</li>
      </ul>
    </section>

    <!-- TRANSPORT FACILITY -->
    <section class="card">
      <h2>TRANSPORT FACILITY</h2>
      <ul>
        <li>
           The Van association will not permit the cancellation of van
          facilities in the middle of the year.
        </li>
        <li>
           The school has no van of it's own all vans are plied by private van
          owners.
        </li>
        <li>
           The school will ensure that ONLY vans with school permits and
          insurance will be allowed to ferry students.
        </li>
        <li>
           In case of any dispute between the van owners and parents please
          resolve the same amicably. In case both parties are unable to reach an
          understanding, please contact of the administrative department to help
          mediate. However, it is the collective responsibility of the school
          and parents to ensure safety of students to and from school.
        </li>
      </ul>
    </section>

    <!-- MOBILE PHONES -->
    <section class="card">
      <h2>STRICT PROHIBITION ON MOBILE PHONES FOR STUDENTS IN SCHOOL</h2>
      <p>
        Youngsters may be inseparable from their mobile phones, but the
        students will have to learn to live without the omnipresent handset as
        long as they are in the school campus. If there is a necessity to carry
        the phone to school on any particular day, then kindly send a diary note
        for the same. It is expected from a student to switch off the phone and
        hand it over to their class teacher and collect it while leaving.
      </p>
      <ul>
        <li>
           In case any student is caught with the mobile phone in the school
          campus the phones will be confiscated and returned only after a week.
        </li>
        <li>
           In case the student is found using the phone in the school premises
          the phone will be confiscated for one month and the student will be
          suspended for 3 days.
        </li>
        <li>
           In case of a second violation of the school rules , the phone will
          be confiscated for an unlimited period of time
        </li>
      </ul>
    </section>

    <!-- TUITIONS -->
    <section class="card">
      <h2>TUTIONS</h2>
      <ul>
        <li>
           Hills High School strictly prohibits students getting tuitions from
          teachers of the school.
        </li>
        <li>
           If found that the practice of getting tuition continues even after
          the warning. The student as well as the teacher will be terminated
          from schooling.
        </li>
        <li>
           In case any students require extra guidance in any subject, please
          contact the supervisor to arrange extra classes in school itself with
          no additional fee.
        </li>
      </ul>
    </section>

    <div class="section-divider"></div>

    <!-- DISCIPLINE & GRIEVANCE -->
    <section class="card">
      <h2>DISCIPLINE &amp; GRIEVANCE MANAGEMENT</h2>
      <p>
        The school is a place to learn and grow. To make it run smoothly the
        following disciplinary measures may be adopted in dealing with students
        who are involved in serious offences of misbehavior.
      </p>
      <ul>
        <li>i) Reprimand &amp; counseling</li>
        <li>ii) Written warning to maximum of three.</li>
        <li>iii) Suspension</li>
        <li>iv) Removal / Dismissal.</li>
      </ul>
      <p>The School may terminate or advice for a change of schooling of Students on the following grounds :</p>
      <ul>
        <li> Repeated indiscipline.</li>
        <li> Unsatisfactory progress in academics.</li>
        <li>
           Repeated absence without leave or unexplained leave for more than 20
          consecutive days.
        </li>
        <li>
           The school reserves the right to terminate any student whose
          progress in studies is unsatisfactory or whose conduct is a bad
          example for other children.
        </li>
        <li>
           Anyone with a genuine grievance may approach the School Principal
          directly or drop the complaint in writing in the complaint box.
        </li>
        <li>
           The Disciplinary Committee and the Redressal Grievances Committee
          are the decision makers with regard to any disciplinary actions. The
          Committees decision is final and binding to all concerned parties.
        </li>
      </ul>
    </section>

    <p class="footer-note">
      Thank You for taking time and reading this document !!
    </p>
  </div>
</body>
</html>
