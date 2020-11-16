-- phpMyAdmin SQL Dump
-- version 4.8.5
-- https://www.phpmyadmin.net/
--
-- Хост: 127.0.0.1:3306
-- Время создания: Ноя 16 2020 г., 09:48
-- Версия сервера: 10.3.13-MariaDB
-- Версия PHP: 7.2.10

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET AUTOCOMMIT = 0;
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- База данных: `shinryad_product_import`
--

-- --------------------------------------------------------

--
-- Структура таблицы `imp_product_full`
--

CREATE TABLE `imp_product_full` (
  `id` bigint(11) NOT NULL,
  `provider_id` int(11) NOT NULL COMMENT 'Ид. поставщика',
  `type_id` int(11) NOT NULL DEFAULT 0 COMMENT 'Тип продукта',
  `code` varchar(30) NOT NULL COMMENT 'Идентификатор позиции (внутри выгрузки поставщика)',
  `marka` varchar(100) NOT NULL COMMENT 'Марка',
  `model` varchar(100) NOT NULL COMMENT 'Модель',
  `size` varchar(255) DEFAULT NULL COMMENT 'Типоразмер',
  `full_title` varchar(255) NOT NULL COMMENT 'Полное наименование',
  `price_opt` decimal(10,0) NOT NULL DEFAULT 0 COMMENT 'Оптовая цена',
  `price` decimal(10,0) NOT NULL DEFAULT 0 COMMENT 'Розничная цена',
  `count` int(11) NOT NULL DEFAULT 0 COMMENT 'Количество',
  `params` text DEFAULT NULL COMMENT 'Параметры'
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Структура таблицы `imp_product_type`
--

CREATE TABLE `imp_product_type` (
  `id` int(11) NOT NULL,
  `name` varchar(30) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Дамп данных таблицы `imp_product_type`
--

INSERT INTO `imp_product_type` (`id`, `name`) VALUES
(1, 'Шины'),
(2, 'Диски');

-- --------------------------------------------------------

--
-- Структура таблицы `imp_provider`
--

CREATE TABLE `imp_provider` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Дамп данных таблицы `imp_provider`
--

INSERT INTO `imp_provider` (`id`, `name`) VALUES
(1, '4tochki'),
(2, 'KolesaDarom');

--
-- Индексы сохранённых таблиц
--

--
-- Индексы таблицы `imp_product_full`
--
ALTER TABLE `imp_product_full`
  ADD PRIMARY KEY (`id`),
  ADD KEY `provider_id` (`provider_id`),
  ADD KEY `type_id` (`type_id`) USING BTREE,
  ADD KEY `size` (`size`),
  ADD KEY `marka` (`marka`),
  ADD KEY `model` (`model`);

--
-- Индексы таблицы `imp_product_type`
--
ALTER TABLE `imp_product_type`
  ADD PRIMARY KEY (`id`);

--
-- Индексы таблицы `imp_provider`
--
ALTER TABLE `imp_provider`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT для сохранённых таблиц
--

--
-- AUTO_INCREMENT для таблицы `imp_product_full`
--
ALTER TABLE `imp_product_full`
  MODIFY `id` bigint(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `imp_product_type`
--
ALTER TABLE `imp_product_type`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT для таблицы `imp_provider`
--
ALTER TABLE `imp_provider`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- Ограничения внешнего ключа сохраненных таблиц
--

--
-- Ограничения внешнего ключа таблицы `imp_product_full`
--
ALTER TABLE `imp_product_full`
  ADD CONSTRAINT `imp_product_full_ibfk_1` FOREIGN KEY (`provider_id`) REFERENCES `imp_provider` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `imp_product_full_ibfk_2` FOREIGN KEY (`type_id`) REFERENCES `imp_product_type` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
