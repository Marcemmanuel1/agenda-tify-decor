-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Hôte : 127.0.0.1
-- Généré le : ven. 12 sep. 2025 à 10:42
-- Version du serveur : 10.4.32-MariaDB
-- Version de PHP : 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de données : `agenda_rdv`
--

-- --------------------------------------------------------

--
-- Structure de la table `clients`
--

CREATE TABLE `clients` (
  `id` int(11) NOT NULL,
  `nom` varchar(100) NOT NULL,
  `prenom` varchar(100) NOT NULL,
  `genre` enum('M','F','Autre') NOT NULL,
  `commune` varchar(100) NOT NULL,
  `telephone` varchar(20) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `clients`
--

INSERT INTO `clients` (`id`, `nom`, `prenom`, `genre`, `commune`, `telephone`, `created_at`) VALUES
(3, 'emmanuel', 'ruben', 'M', 'bingerville', '0710081552', '2025-09-11 14:12:26');

-- --------------------------------------------------------

--
-- Structure de la table `logs_suppression`
--

CREATE TABLE `logs_suppression` (
  `id` int(11) NOT NULL,
  `rdv_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `motif_suppression` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `notifications`
--

CREATE TABLE `notifications` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `message` text NOT NULL,
  `lien` varchar(255) DEFAULT NULL,
  `lue` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `rendezvous`
--

CREATE TABLE `rendezvous` (
  `id` int(11) NOT NULL,
  `client_id` int(11) NOT NULL,
  `planificateur_id` int(11) NOT NULL,
  `agent_id` int(11) DEFAULT NULL,
  `date_contact` datetime NOT NULL,
  `date_rdv` datetime NOT NULL,
  `statut_paiement` enum('Payé','Impayé') DEFAULT 'Impayé',
  `motif` text DEFAULT NULL,
  `statut_rdv` enum('En attente','Effectué','Annulé','Modifié') DEFAULT 'En attente',
  `notes_agent` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `statut` enum('en_attente','confirme','annule') DEFAULT 'en_attente'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `rendezvous`
--

INSERT INTO `rendezvous` (`id`, `client_id`, `planificateur_id`, `agent_id`, `date_contact`, `date_rdv`, `statut_paiement`, `motif`, `statut_rdv`, `notes_agent`, `created_at`, `statut`) VALUES
(0, 3, 2, NULL, '2025-09-11 16:06:00', '2025-09-23 14:06:00', 'Payé', 'prise de rendez-vous pour un entretient dans le bureau \r\n', 'En attente', NULL, '2025-09-11 14:12:26', 'en_attente');

-- --------------------------------------------------------

--
-- Structure de la table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `nom` varchar(100) NOT NULL,
  `prenom` varchar(100) NOT NULL,
  `email` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('super_admin','planificateur','agent') NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `active` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `users`
--

INSERT INTO `users` (`id`, `nom`, `prenom`, `email`, `password`, `role`, `created_at`, `active`) VALUES
(1, 'Brou', 'marc emmanuel', 'broumarc175@gmail.com', '$2y$10$6htKeMi8dSflI3w0zE5Jr.fBZ9opJ8YXDjgwV7wQR3KGIHmPEPW2W', 'super_admin', '2025-09-11 11:55:49', 1),
(2, 'marc', 'brou', 'broumarc@gmail.com', '$2y$10$.huC563.fjByjer9UDKt6uWz04IL8j7MCSHDuXkBxrdKVMLBThgu.', 'planificateur', '2025-09-11 11:58:42', 1),
(3, 'brou', 'ruben', 'ruben@gmail.com', '$2y$10$nsXUSCa6.bm4AokrhukSIORISVY8yKEZr0PT9RLcNQ.1S7.g8/kDW', 'agent', '2025-09-11 14:17:45', 1);

--
-- Index pour les tables déchargées
--

--
-- Index pour la table `clients`
--
ALTER TABLE `clients`
  ADD PRIMARY KEY (`id`);

--
-- Index pour la table `logs_suppression`
--
ALTER TABLE `logs_suppression`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Index pour la table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Index pour la table `rendezvous`
--
ALTER TABLE `rendezvous`
  ADD PRIMARY KEY (`id`),
  ADD KEY `client_id` (`client_id`),
  ADD KEY `planificateur_id` (`planificateur_id`),
  ADD KEY `agent_id` (`agent_id`);

--
-- Index pour la table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT pour les tables déchargées
--

--
-- AUTO_INCREMENT pour la table `clients`
--
ALTER TABLE `clients`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT pour la table `logs_suppression`
--
ALTER TABLE `logs_suppression`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `rendezvous`
--
ALTER TABLE `rendezvous`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT pour la table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- Contraintes pour les tables déchargées
--

--
-- Contraintes pour la table `logs_suppression`
--
ALTER TABLE `logs_suppression`
  ADD CONSTRAINT `logs_suppression_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Contraintes pour la table `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Contraintes pour la table `rendezvous`
--
ALTER TABLE `rendezvous`
  ADD CONSTRAINT `rendezvous_ibfk_1` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`),
  ADD CONSTRAINT `rendezvous_ibfk_2` FOREIGN KEY (`planificateur_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `rendezvous_ibfk_3` FOREIGN KEY (`agent_id`) REFERENCES `users` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
